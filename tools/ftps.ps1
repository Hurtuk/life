<#
  ftps.ps1 - couche FTPS reutilisable pour les livraisons sur louiecinephile.fr
  Sourcer ce fichier puis utiliser Send-RemoteFile / Get-RemoteList / Remove-RemoteFile.

  LWS sert un certificat mutualise *.lwspanel.com sur son FTP : on adresse donc
  un nom couvert par ce certificat tout en se connectant a l'IP du domaine.
  Le TLS est integralement verifie (pas de --insecure).
#>

$script:EnvFile = Join-Path $PSScriptRoot '..\.secrets\deploy.env'

function Get-DeployConfig {
    if (-not (Test-Path $script:EnvFile)) { throw "Fichier de configuration absent : $script:EnvFile" }
    $cfg = @{}
    Get-Content $script:EnvFile -Encoding UTF8 |
        Where-Object { $_ -match '^\s*([A-Z0-9_]+)\s*=\s*(.*)$' } |
        ForEach-Object { $cfg[$Matches[1]] = $Matches[2].Trim() }
    foreach ($k in 'FTP_USER','FTP_PASS','FTP_TLS_HOST','FTP_IP','FTP_REMOTE_DIR') {
        if (-not $cfg[$k]) { throw "Cle manquante dans deploy.env : $k" }
    }
    return $cfg
}

<#
  Encode chaque segment du chemin distant. Un nom contenant un espace ou un
  caractere reserve fait echouer curl ("URL using bad/illegal format"), et le
  transfert s'arrete au milieu. curl decode le %XX avant d'emettre la commande
  FTP : le fichier distant garde son nom d'origine.
#>
function ConvertTo-FtpUrlPath {
    param([string]$Path)
    return (($Path -split '/' | ForEach-Object {
        if ($_ -eq '') { $_ } else { [Uri]::EscapeDataString($_) }
    }) -join '/')
}

function Invoke-Ftps {
    param([string[]]$ExtraArgs, [string]$UrlPath = '')
    $cfg = Get-DeployConfig
    $base = "ftp://$($cfg.FTP_TLS_HOST)"
    # Seule l'URL est encodee : un argument --quote (DELE ...) attend le chemin brut.
    $UrlPath = ConvertTo-FtpUrlPath $UrlPath
    $args = @(
        '--silent', '--show-error',
        '--ssl-reqd',
        '--connect-to', "$($cfg.FTP_TLS_HOST):21:$($cfg.FTP_IP):21",
        '--user', "$($cfg.FTP_USER):$($cfg.FTP_PASS)",
        '--connect-timeout', '20', '--max-time', '180'
    ) + $ExtraArgs + @("$base$UrlPath")
    # 2>&1 sur un executable natif emballe chaque ligne de stderr dans un
    # ErrorRecord : sous $ErrorActionPreference = 'Stop', le moindre
    # avertissement de curl devient une exception terminante et le script
    # s'arrete avant meme d'avoir lu le code de retour. On neutralise la
    # preference le temps de l'appel, et on juge sur $LASTEXITCODE.
    $prefPrecedente = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $out = & curl.exe @args 2>&1
    } finally {
        $ErrorActionPreference = $prefPrecedente
    }
    return [PSCustomObject]@{ ExitCode = $LASTEXITCODE; Output = $out }
}

function Get-RemoteList {
    param([string]$Path = '/')
    if (-not $Path.EndsWith('/')) { $Path += '/' }
    $r = Invoke-Ftps -ExtraArgs @('--list-only') -UrlPath $Path
    if ($r.ExitCode -ne 0) { throw "Listing $Path echoue (curl $($r.ExitCode)) : $($r.Output)" }
    return $r.Output | Where-Object { $_ -and $_ -notin '.', '..' }
}

<#
  Taille d'un fichier distant, ou -1 s'il est absent ou illisible.
  --head sur une URL FTP se traduit par une commande SIZE.
#>
function Get-RemoteFileSize {
    param([Parameter(Mandatory)][string]$RemotePath)
    $cfg = Get-DeployConfig
    # Pas de --show-error : l'absence du fichier est un resultat, pas un incident.
    $out = & curl.exe --silent --ssl-reqd --head `
        --connect-to "$($cfg.FTP_TLS_HOST):21:$($cfg.FTP_IP):21" `
        --user "$($cfg.FTP_USER):$($cfg.FTP_PASS)" `
        --connect-timeout 20 --max-time 60 `
        "ftp://$($cfg.FTP_TLS_HOST)$(ConvertTo-FtpUrlPath $RemotePath)"
    if ($LASTEXITCODE -ne 0) { return -1 }
    foreach ($ligne in $out) {
        if ($ligne -match '^Content-Length:\s*(\d+)') { return [int64]$Matches[1] }
    }
    return -1
}

function Send-RemoteFile {
    param(
        [Parameter(Mandatory)][string]$LocalPath,
        [Parameter(Mandatory)][string]$RemotePath   # ex: /theatre/api/index.php
    )
    if (-not (Test-Path $LocalPath)) { throw "Fichier local absent : $LocalPath" }
    $r = Invoke-Ftps -ExtraArgs @('--ftp-create-dirs', '--upload-file', $LocalPath) -UrlPath $RemotePath
    if ($r.ExitCode -ne 0) {
        # LWS repond 451 a la cloture de certains transferts alors que le
        # fichier est correctement ecrit — systematiquement sur le bundle
        # principal, seul fichier volumineux du build. On ne se fie donc pas au
        # code de retour seul : on va comparer les tailles avant de conclure.
        $attendue = (Get-Item $LocalPath).Length
        $distante = Get-RemoteFileSize $RemotePath
        if ($distante -ne $attendue) {
            throw "Envoi de $RemotePath echoue (curl $($r.ExitCode)) : $($r.Output)"
        }
        Write-Host ("       avertissement serveur ignore, {0} bien ecrit ({1:N0} o)" -f $RemotePath, $attendue) -ForegroundColor DarkGray
    }
    return $RemotePath
}

function Remove-RemoteFile {
    param([Parameter(Mandatory)][string]$RemotePath)
    $dir = ([IO.Path]::GetDirectoryName($RemotePath) -replace '\\', '/')
    if (-not $dir.EndsWith('/')) { $dir += '/' }
    $r = Invoke-Ftps -ExtraArgs @('--quote', "DELE $RemotePath") -UrlPath $dir
    if ($r.ExitCode -ne 0) { throw "Suppression de $RemotePath echouee (curl $($r.ExitCode)) : $($r.Output)" }
}

function Test-FtpsConnection {
    $cfg = Get-DeployConfig
    $items = Get-RemoteList '/'
    return [PSCustomObject]@{
        Hote            = $cfg.FTP_IP
        Utilisateur     = $cfg.FTP_USER
        ElementsRacine  = $items.Count
        RepertoireCible = $cfg.FTP_REMOTE_DIR
    }
}

<#
  Listing recursif d'un dossier distant.

  Utilise LIST et non NLST (--list-only) : il faut distinguer les fichiers des
  dossiers pour descendre, et voir les fichiers caches, qu'un nettoyage ne doit
  jamais toucher.

  Renvoie des objets {Chemin, Relatif, Taille, Cache}.
#>
function Get-RemoteFiles {
    param(
        [Parameter(Mandatory)][string]$Path,
        [string]$Racine
    )
    if (-not $Racine) { $Racine = $Path.TrimEnd('/') + '/' }
    if (-not $Path.EndsWith('/')) { $Path += '/' }

    $cfg = Get-DeployConfig
    $out = & curl.exe --silent --show-error --ssl-reqd `
        --connect-to "$($cfg.FTP_TLS_HOST):21:$($cfg.FTP_IP):21" `
        --user "$($cfg.FTP_USER):$($cfg.FTP_PASS)" `
        --connect-timeout 20 --max-time 120 `
        "ftp://$($cfg.FTP_TLS_HOST)$(ConvertTo-FtpUrlPath $Path)"
    if ($LASTEXITCODE -ne 0) { throw "Listing $Path echoue (curl $LASTEXITCODE) : $out" }

    $fichiers = @()
    foreach ($ligne in $out) {
        if ($ligne -notmatch '^([dl\-])\S*\s+\d+\s+\S+\s+\S+\s+(\d+)\s+\S+\s+\S+\s+\S+\s+(.+)$') { continue }
        $nom = $Matches[3]
        if ($nom -in '.', '..') { continue }
        $chemin = "$Path$nom"
        if ($Matches[1] -eq 'd') {
            $fichiers += Get-RemoteFiles -Path $chemin -Racine $Racine
        } else {
            $fichiers += [pscustomobject]@{
                Chemin  = $chemin
                Relatif = $chemin.Substring($Racine.Length)
                Taille  = [int64]$Matches[2]
                Cache   = $nom.StartsWith('.')
            }
        }
    }
    return $fichiers
}
