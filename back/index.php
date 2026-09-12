<?php
	include './php/datamodel.php';

	global $db;

	$flash = null;
	$activity = null;
	$chapter = null;
	$editActivityId = null;
	$editChapterId = null;

	if (isset($_POST['editActivity'])) {
		$editActivityId = $_POST['editActivity'];
	} else if (isset($_POST['editChapter'])) {
		$editChapterId = $_POST['editChapter'];
	} else if (isset($_POST['element'])) {
		if ($_POST['element'] == "activity") {
			// Edit activity
			$args = array(
				!empty($_POST['title']) ? $_POST['title'] : '',
				!empty($_POST['structure']) ? $_POST['structure'] : '',
				!empty($_POST['role']) ? $_POST['role'] : null,
				$_POST['type'],
				!empty($_POST['tag']) ? $_POST['tag'] : null,
				$_POST['startDate'],
				!empty($_POST['endDate']) ? $_POST['endDate'] : null,
				!empty($_POST['comment']) ? $_POST['comment'] : null
			);
			if ($_POST['id'] == "-1") {
				$db->update("INSERT INTO activities (title, idStructure, role, type, idTag, startDate, endDate, comment) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", $args);
				$editActivityId = $db->selectVal('SELECT id FROM activities ORDER BY id DESC LIMIT 1')['id'];
			} else {
				$args[] = $_POST['id'];
				$db->update("UPDATE activities SET title = ?, idStructure = ?, role = ?, type = ?, idTag = ?, startDate = ?, endDate = ?, comment = ? WHERE id = ?", $args);
				$editActivityId = $_POST['id'];
			}
			$flash = "Activité enregistrée.";
		} else if ($_POST['element'] == "chapter") {
			// Edit chapter
			$args = array(
				!empty($_POST['title']) ? $_POST['title'] : '',
				$_POST['content'],
				$_POST['startDate'],
				$_POST['endDate'],
				!empty($_POST['narrated']) ? "1" : "0",
				!empty($_POST['activity']) ? $_POST['activity'] : null,
				!empty($_POST['loveStory']) ? $_POST['loveStory'] : null
			);
			$people = explode(', ', $_POST['people']);
			$tags = $_POST['tags'];
			if ($_POST['id'] == "-1") {
				$db->update("INSERT INTO chapters (title, content, startDate, endDate, narrated, idActivity, idLoveStory) VALUES (?, ?, ?, ?, ?, ?, ?)", $args);
				$id = $db->selectVal('SELECT id FROM chapters ORDER BY id DESC LIMIT 1')['id'];
			} else {
				$id = $_POST['id'];
				$args[] = $id;
				$db->update("UPDATE chapters SET title = ?, content = ?, startDate = ?, endDate = ?, narrated = ?, idActivity = ?, idLoveStory = ? WHERE id = ?", $args);
				$db->update("DELETE FROM chapter_people WHERE idChapter = ?", array($id));
				$db->update("DELETE FROM chapter_tags WHERE idChapter = ?", array($id));
			}
			if ($people && !empty($people[0])) {
				foreach ($people as $p) {
					$person = $db->selectVal("SELECT id FROM people WHERE CONCAT(firstname, ' ', lastname) = ?", array($p));
					if (!$person) {
						$db->update("INSERT INTO people (firstname, lastname) VALUES (?, ?)", explode(' ', $p));
						$person = $db->selectVal("SELECT id FROM people WHERE CONCAT(firstname, ' ', lastname) = ?", array($p));
					}
					$db->update("INSERT INTO chapter_people (idChapter, idPerson) VALUES (?, ?)", array($id, $person['id']));
				}
			}
			$i = 0;
			foreach (($_POST['tags'] ?? array()) as $t) {
				if ($t) {
					$db->update("INSERT INTO chapter_tags (idChapter, idTag, priority) VALUES (?, ?, ?)", array($id, $t, $i++));
				}
			}
			$editChapterId = $id;
			$flash = "Chapitre enregistré.";
		}
	}

	// Chargement de l'élément à éditer : sélection depuis la liste, ou relecture
	// de ce qui vient d'être enregistré pour pouvoir enchaîner les retouches.
	if ($editActivityId !== null) {
		$activity = $db->selectVal("SELECT id, title, idStructure, role, type, idTag, startDate, endDate, comment FROM activities WHERE id = ?", array($editActivityId));
	}
	if ($editChapterId !== null) {
		$chapter = $db->selectVal("SELECT c.id, c.title, c.startDate, c.endDate, c.narrated, c.idActivity, c.idLoveStory,
										GROUP_CONCAT(DISTINCT CONCAT(p.firstname, ' ', p.lastname) ORDER BY p.lastname, p.firstname SEPARATOR ', ') AS people, c.content,
										GROUP_CONCAT(DISTINCT t.id ORDER BY ct.priority SEPARATOR ',') AS tags
										FROM chapters c
										LEFT JOIN (chapter_people cp
											INNER JOIN people p
											ON p.id = cp.idPerson)
										ON cp.idChapter = c.id
										LEFT JOIN (chapter_tags ct
											INNER JOIN tags t
											ON t.id = ct.idTag)
										ON ct.idChapter = c.id
										WHERE c.id = ?
										GROUP BY c.id", array($editChapterId));
		if ($chapter) {
			$chapter['narrated'] = $chapter['narrated'] == "1";
			$chapter['tags'] = explode(',', $chapter['tags'] ?? '');
		}
	}

	// Onglet ouvert au chargement : celui de l'élément en cours d'édition.
	$tab = $chapter ? 'chapter' : 'activity';
	if (isset($_GET['tab']) && $_GET['tab'] == 'chapter') {
		$tab = 'chapter';
	} else if (isset($_GET['tab']) && $_GET['tab'] == 'activity') {
		$tab = 'activity';
	}

	// Échappement systématique : un titre contenant une apostrophe ou un chevron
	// tronquait le champ du formulaire.
	function h($value) {
		return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
	}

	// "12/06/2019", ou "—" si la date est vide (activité toujours en cours).
	function fdate($value) {
		if (empty($value)) {
			return '—';
		}
		return date('d/m/Y', strtotime($value));
	}

	$TYPES = array(
		'school' => 'Scolarité',
		'job' => 'Travail',
		'hobby' => 'Activités',
		'volunteering' => 'Bénévolat'
	);

	$activities = $db->select("SELECT id, startDate, endDate, title, comment, type FROM activities ORDER BY startDate DESC");
	$chapters = $db->select("SELECT id, startDate, title, narrated FROM chapters ORDER BY startDate DESC");
	$structures = $db->select("SELECT id, title FROM structures ORDER BY title");
	$allTags = $db->select("SELECT id, name FROM tags ORDER BY name");
	$loveStories = $db->select("SELECT ls.id, startDate, endDate, CONCAT(firstname, ' ', lastname) AS title FROM love_stories ls INNER JOIN people p ON p.id = idPerson ORDER BY startDate DESC");
	$allPeople = $db->select("SELECT CONCAT(firstname, ' ', lastname) AS name FROM people ORDER BY lastname, firstname");
?>
<!doctype html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<title>Life · back-office</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="icon" type="image/x-icon" href="../life/favicon.ico">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=EB+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Oswald:wght@300;400;500;600&display=swap" rel="stylesheet">
	<style>
		:root {
			--paper: #f2f0ea;
			--card: #fffefb;
			--ink: #26241f;
			--muted: #7e786c;
			--line: #e3ded4;
			--accent: #8c2f39;
			--accent-ink: #fff;
			--accent-soft: #f6e9ea;
			--radius: 12px;
			--shadow: 0 1px 2px rgba(0, 0, 0, .04), 0 10px 30px rgba(0, 0, 0, .05);
			--main-font: 'EB Garamond', Georgia, serif;
			--label-font: 'Oswald', 'Segoe UI', sans-serif;
		}

		@media (prefers-color-scheme: dark) {
			:root {
				--paper: #1b1a18;
				--card: #242220;
				--ink: #ece8e0;
				--muted: #9a9287;
				--line: #383530;
				--accent: #d4707b;
				--accent-ink: #23100f;
				--accent-soft: #3a2528;
				--shadow: 0 1px 2px rgba(0, 0, 0, .3), 0 10px 30px rgba(0, 0, 0, .35);
			}
		}

		* { box-sizing: border-box; }

		body {
			margin: 0;
			background: var(--paper);
			color: var(--ink);
			font-family: var(--main-font);
			font-size: 17px;
			line-height: 1.5;
		}

		/* ---------------------------------------------------------- Barre de titre */

		.topbar {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 1rem;
			flex-wrap: wrap;
			padding: .9rem 1.5rem;
			background: var(--card);
			border-bottom: 1px solid var(--line);
			position: sticky;
			top: 0;
			z-index: 10;
		}

		.topbar h1 {
			margin: 0;
			font-size: 1.5rem;
			font-weight: 600;
			letter-spacing: .01em;
		}

		.topbar h1 span {
			font-family: var(--label-font);
			font-size: .7rem;
			font-weight: 400;
			text-transform: uppercase;
			letter-spacing: .18em;
			color: var(--muted);
			margin-left: .6rem;
			vertical-align: .25em;
		}

		.tabs {
			display: flex;
			gap: .25rem;
			background: var(--paper);
			border: 1px solid var(--line);
			border-radius: 999px;
			padding: .2rem;
		}

		.tabs button {
			font-family: var(--label-font);
			font-size: .78rem;
			font-weight: 400;
			text-transform: uppercase;
			letter-spacing: .12em;
			color: var(--muted);
			background: none;
			border: 0;
			border-radius: 999px;
			padding: .45rem 1.1rem;
			cursor: pointer;
			transition: background .15s, color .15s;
		}

		.tabs button:hover { color: var(--ink); }

		.tabs button[aria-selected="true"] {
			background: var(--accent);
			color: var(--accent-ink);
		}

		/* ------------------------------------------------------------------ Flash */

		.flash {
			margin: 0;
			padding: .7rem 1.5rem;
			background: var(--accent-soft);
			color: var(--accent);
			font-family: var(--label-font);
			font-size: .82rem;
			text-transform: uppercase;
			letter-spacing: .12em;
			border-bottom: 1px solid var(--line);
		}

		/* ----------------------------------------------------------------- Layout */

		main {
			padding: 1.5rem;
			max-width: 1500px;
			margin: 0 auto;
		}

		.pane {
			display: grid;
			grid-template-columns: 330px minmax(0, 1fr);
			gap: 1.5rem;
			align-items: start;
		}

		.pane[hidden] { display: none; }

		.card {
			background: var(--card);
			border: 1px solid var(--line);
			border-radius: var(--radius);
			box-shadow: var(--shadow);
		}

		/* ------------------------------------------------------------- Liste latérale */

		.picker {
			position: sticky;
			top: 5rem;
			display: flex;
			flex-direction: column;
			max-height: calc(100vh - 7rem);
			overflow: hidden;
		}

		.picker-head {
			display: flex;
			gap: .5rem;
			padding: .75rem;
			border-bottom: 1px solid var(--line);
		}

		.picker-head input {
			flex: 1;
			min-width: 0;
		}

		.list {
			overflow-y: auto;
			padding: .4rem;
			margin: 0;
		}

		.item {
			display: block;
			width: 100%;
			text-align: left;
			background: none;
			border: 0;
			border-radius: 8px;
			padding: .5rem .6rem;
			cursor: pointer;
			color: inherit;
			font: inherit;
			transition: background .12s;
		}

		.item[hidden] { display: none; }

		.item:hover { background: var(--paper); }

		.item.current {
			background: var(--accent-soft);
			box-shadow: inset 3px 0 0 var(--accent);
		}

		.item-title {
			display: block;
			font-size: .95rem;
			line-height: 1.3;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		.item-meta {
			display: flex;
			align-items: center;
			gap: .45rem;
			font-family: var(--label-font);
			font-size: .68rem;
			font-weight: 300;
			text-transform: uppercase;
			letter-spacing: .08em;
			color: var(--muted);
		}

		.draft {
			color: var(--accent);
			border: 1px solid currentColor;
			border-radius: 999px;
			padding: 0 .4em;
			font-size: .9em;
		}

		.empty {
			padding: 1rem .75rem;
			color: var(--muted);
			font-style: italic;
		}

		/* -------------------------------------------------------------- Formulaire */

		.editor { padding: 1.25rem 1.5rem 1.5rem; }

		.editor h2 {
			margin: 0 0 1.1rem;
			font-size: 1.25rem;
			font-weight: 600;
			padding-bottom: .6rem;
			border-bottom: 1px solid var(--line);
		}

		.editor h2 small {
			font-family: var(--label-font);
			font-size: .68rem;
			font-weight: 300;
			text-transform: uppercase;
			letter-spacing: .14em;
			color: var(--muted);
			margin-left: .6rem;
		}

		.fields {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
			gap: .9rem 1.1rem;
		}

		.field.wide { grid-column: 1 / -1; }

		label {
			display: block;
			font-family: var(--label-font);
			font-size: .72rem;
			font-weight: 400;
			text-transform: uppercase;
			letter-spacing: .12em;
			color: var(--muted);
			margin-bottom: .3rem;
		}

		input[type=text], input[type=date], input[type=search], select, textarea {
			width: 100%;
			font-family: var(--main-font);
			font-size: 1rem;
			color: var(--ink);
			background: var(--paper);
			border: 1px solid var(--line);
			border-radius: 8px;
			padding: .5rem .65rem;
			transition: border-color .15s, box-shadow .15s;
		}

		input:focus, select:focus, textarea:focus {
			outline: none;
			border-color: var(--accent);
			box-shadow: 0 0 0 3px var(--accent-soft);
		}

		textarea {
			min-height: 8rem;
			resize: vertical;
			line-height: 1.6;
		}

		textarea[name=content] { min-height: 20rem; }

		.check {
			display: flex;
			align-items: center;
			gap: .5rem;
			margin: 0;
			padding-top: 1.35rem;
			text-transform: none;
			letter-spacing: 0;
			font-family: var(--main-font);
			font-size: 1rem;
			color: var(--ink);
			cursor: pointer;
		}

		.check input { width: 1.1rem; height: 1.1rem; accent-color: var(--accent); }

		fieldset.tags {
			grid-column: 1 / -1;
			border: 1px solid var(--line);
			border-radius: var(--radius);
			padding: .8rem 1rem 1rem;
			margin: 0;
		}

		fieldset.tags legend {
			font-family: var(--label-font);
			font-size: .72rem;
			text-transform: uppercase;
			letter-spacing: .12em;
			color: var(--muted);
			padding: 0 .4rem;
		}

		.tag-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
			gap: .6rem;
		}

		.people-add {
			display: flex;
			gap: .5rem;
			margin-top: .4rem;
		}

		.people-add input { flex: 1; min-width: 0; }

		/* ----------------------------------------------------------------- Boutons */

		.btn {
			font-family: var(--label-font);
			font-size: .78rem;
			font-weight: 400;
			text-transform: uppercase;
			letter-spacing: .12em;
			border-radius: 999px;
			padding: .5rem 1.2rem;
			border: 1px solid var(--accent);
			background: var(--accent);
			color: var(--accent-ink);
			cursor: pointer;
			text-decoration: none;
			display: inline-block;
			transition: filter .15s;
		}

		.btn:hover { filter: brightness(1.1); }

		.btn.ghost {
			background: none;
			color: var(--accent);
		}

		.actions {
			display: flex;
			align-items: center;
			gap: 1rem;
			margin-top: 1.4rem;
			padding-top: 1.1rem;
			border-top: 1px solid var(--line);
		}

		.hint {
			font-family: var(--label-font);
			font-size: .7rem;
			font-weight: 300;
			text-transform: uppercase;
			letter-spacing: .1em;
			color: var(--muted);
		}

		@media screen and (max-width: 900px) {
			.pane { grid-template-columns: 1fr; }

			.picker {
				position: static;
				max-height: 22rem;
			}
		}
	</style>
</head>
<body>
	<header class="topbar">
		<h1>Life <span>back-office</span></h1>
		<nav class="tabs" role="tablist">
			<button type="button" role="tab" data-tab="activity" aria-selected="<?= $tab == 'activity' ? 'true' : 'false' ?>" aria-controls="pane-activity">Activités</button>
			<button type="button" role="tab" data-tab="chapter" aria-selected="<?= $tab == 'chapter' ? 'true' : 'false' ?>" aria-controls="pane-chapter">Chapitres</button>
		</nav>
	</header>

	<?php if ($flash) { ?><p class="flash"><?= h($flash) ?></p><?php } ?>

	<main>
		<!-- ------------------------------------------------------- Activités -->
		<section class="pane" id="pane-activity" <?= $tab == 'activity' ? '' : 'hidden' ?>>
			<aside class="picker card">
				<div class="picker-head">
					<input type="search" placeholder="Filtrer les activités…" data-filter="list-activity">
					<a class="btn ghost" href="?tab=activity">Nouvelle</a>
				</div>
				<form method="post" action="" class="list" id="list-activity">
					<?php foreach ($activities as $a) { ?>
						<button type="submit" name="editActivity" value="<?= h($a['id']) ?>"
							class="item<?= $activity && $activity['id'] == $a['id'] ? ' current' : '' ?>">
							<span class="item-title"><?= h($a['title']) ?></span>
							<span class="item-meta">
								<span><?= h(fdate($a['startDate'])) ?> → <?= h(fdate($a['endDate'])) ?></span>
								<?php if (!$a['comment']) { ?><span class="draft">sans texte</span><?php } ?>
							</span>
						</button>
					<?php } ?>
				</form>
			</aside>

			<form method="post" action="" class="editor card">
				<input type="hidden" name="element" value="activity" />
				<input type="hidden" name="id" value="<?= $activity ? h($activity['id'] ?? '') : -1 ?>" />
				<h2>
					<?= $activity ? h($activity['title'] ?? '') : 'Nouvelle activité' ?>
					<small><?= $activity ? 'activité n° ' . h($activity['id'] ?? '') : 'création' ?></small>
				</h2>
				<div class="fields">
					<div class="field wide">
						<label for="a-title">Titre</label>
						<input type="text" id="a-title" name="title" value="<?= h($activity['title'] ?? '') ?>" />
					</div>
					<div class="field">
						<label for="a-structure">Structure</label>
						<select id="a-structure" name="structure">
							<?php foreach ($structures as $s) { ?>
								<option value="<?= h($s['id']) ?>"<?= $activity && $activity['idStructure'] == $s['id'] ? ' selected' : '' ?>><?= h($s['title']) ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="field">
						<label for="a-role">Rôle</label>
						<input type="text" id="a-role" name="role" value="<?= h($activity['role'] ?? '') ?>" />
					</div>
					<div class="field">
						<label for="a-type">Type</label>
						<select id="a-type" name="type">
							<?php foreach ($TYPES as $value => $libelle) { ?>
								<option value="<?= h($value) ?>"<?= $activity && $activity['type'] == $value ? ' selected' : '' ?>><?= h($libelle) ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="field">
						<label for="a-tag">Tag</label>
						<select id="a-tag" name="tag">
							<option></option>
							<?php foreach ($allTags as $t) { ?>
								<option value="<?= h($t['id']) ?>"<?= $activity && $activity['idTag'] == $t['id'] ? ' selected' : '' ?>><?= h($t['name']) ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="field">
						<label for="a-start">Début</label>
						<input type="date" id="a-start" name="startDate" value="<?= h($activity['startDate'] ?? '') ?>" />
					</div>
					<div class="field">
						<label for="a-end">Fin</label>
						<input type="date" id="a-end" name="endDate" value="<?= h($activity['endDate'] ?? '') ?>" />
					</div>
					<div class="field wide">
						<label for="a-comment">Commentaire</label>
						<textarea id="a-comment" name="comment"><?= h($activity['comment'] ?? '') ?></textarea>
					</div>
				</div>
				<div class="actions">
					<button type="submit" class="btn">Enregistrer</button>
					<span class="hint">Ctrl + S</span>
				</div>
			</form>
		</section>

		<!-- ------------------------------------------------------- Chapitres -->
		<section class="pane" id="pane-chapter" <?= $tab == 'chapter' ? '' : 'hidden' ?>>
			<aside class="picker card">
				<div class="picker-head">
					<input type="search" placeholder="Filtrer les chapitres…" data-filter="list-chapter">
					<a class="btn ghost" href="?tab=chapter">Nouveau</a>
				</div>
				<form method="post" action="" class="list" id="list-chapter">
					<?php foreach ($chapters as $c) { ?>
						<button type="submit" name="editChapter" value="<?= h($c['id']) ?>"
							class="item<?= $chapter && $chapter['id'] == $c['id'] ? ' current' : '' ?>">
							<span class="item-title"><?= h($c['title']) ?></span>
							<span class="item-meta">
								<span><?= h(fdate($c['startDate'])) ?></span>
								<?php if ($c['narrated'] == "0") { ?><span class="draft">brouillon</span><?php } ?>
							</span>
						</button>
					<?php } ?>
				</form>
			</aside>

			<form method="post" action="" class="editor card">
				<input type="hidden" name="element" value="chapter" />
				<input type="hidden" name="id" value="<?= $chapter ? h($chapter['id'] ?? '') : -1 ?>" />
				<h2>
					<?= $chapter ? h($chapter['title'] ?? '') : 'Nouveau chapitre' ?>
					<small><?= $chapter ? 'chapitre n° ' . h($chapter['id'] ?? '') : 'création' ?></small>
				</h2>
				<div class="fields">
					<div class="field wide">
						<label for="c-title">Titre</label>
						<input type="text" id="c-title" name="title" value="<?= h($chapter['title'] ?? '') ?>" />
					</div>
					<div class="field">
						<label for="c-start">Début</label>
						<input type="date" id="c-start" name="startDate" value="<?= h($chapter['startDate'] ?? '') ?>" />
					</div>
					<div class="field">
						<label for="c-end">Fin</label>
						<input type="date" id="c-end" name="endDate" value="<?= h($chapter['endDate'] ?? '') ?>" />
					</div>
					<div class="field">
						<label class="check">
							<input type="checkbox" name="narrated" <?= $chapter && $chapter['narrated'] ? 'checked' : '' ?>/>
							Rédigé
						</label>
					</div>
					<div class="field">
						<label for="c-activity">Activité</label>
						<select id="c-activity" name="activity">
							<option></option>
							<?php foreach ($activities as $a) { ?>
								<option value="<?= h($a['id']) ?>"<?= $chapter && $chapter['idActivity'] == $a['id'] ? ' selected' : '' ?>><?= h(fdate($a['startDate'])) ?> → <?= h(fdate($a['endDate'])) ?> : <?= h($a['title']) ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="field">
						<label for="c-love">Romance</label>
						<select id="c-love" name="loveStory">
							<option></option>
							<?php foreach ($loveStories as $l) { ?>
								<option value="<?= h($l['id']) ?>"<?= $chapter && $chapter['idLoveStory'] == $l['id'] ? ' selected' : '' ?>><?= h(fdate($l['startDate'])) ?> → <?= h(fdate($l['endDate'])) ?> : <?= h($l['title']) ?></option>
							<?php } ?>
						</select>
					</div>
					<div class="field wide">
						<label for="c-people">Personnes <span style="text-transform: none; letter-spacing: 0;">(séparées par une virgule)</span></label>
						<input type="text" id="c-people" name="people" value="<?= h($chapter['people'] ?? '') ?>" />
						<div class="people-add">
							<input type="text" id="people-picker" list="people-list" placeholder="Ajouter une personne…" />
							<datalist id="people-list">
								<?php foreach ($allPeople as $p) { ?><option value="<?= h($p['name']) ?>"></option><?php } ?>
							</datalist>
							<button type="button" class="btn ghost" id="people-add">Ajouter</button>
						</div>
					</div>
					<fieldset class="tags">
						<legend>Tags, par ordre de priorité</legend>
						<div class="tag-grid">
							<?php for ($i = 0; $i < 5; $i++) { ?>
								<div>
									<label for="c-tag-<?= $i ?>">Tag <?= $i + 1 ?></label>
									<select id="c-tag-<?= $i ?>" name="tags[]">
										<option></option>
										<?php foreach ($allTags as $t) { ?>
											<option value="<?= h($t['id']) ?>"<?= $chapter && ($chapter['tags'][$i] ?? null) == $t['id'] ? ' selected' : '' ?>><?= h($t['name']) ?></option>
										<?php } ?>
									</select>
								</div>
							<?php } ?>
						</div>
					</fieldset>
					<div class="field wide">
						<label for="c-content">Récit</label>
						<textarea id="c-content" name="content"><?= h($chapter['content'] ?? '') ?></textarea>
					</div>
				</div>
				<div class="actions">
					<button type="submit" class="btn">Enregistrer</button>
					<span class="hint">Ctrl + S</span>
				</div>
			</form>
		</section>
	</main>

	<script>
		// Onglets : les deux volets sont rendus, on n'en montre qu'un.
		var onglets = document.querySelectorAll('.tabs button');
		onglets.forEach(function (bouton) {
			bouton.addEventListener('click', function () {
				onglets.forEach(function (b) {
					var actif = b === bouton;
					b.setAttribute('aria-selected', actif);
					document.getElementById(b.getAttribute('aria-controls')).hidden = !actif;
				});
			});
		});

		// Filtre des listes, insensible à la casse et aux accents.
		function normaliser(texte) {
			return texte.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
		}

		document.querySelectorAll('[data-filter]').forEach(function (champ) {
			var liste = document.getElementById(champ.getAttribute('data-filter'));
			champ.addEventListener('input', function () {
				var recherche = normaliser(champ.value.trim());
				liste.querySelectorAll('.item').forEach(function (item) {
					item.hidden = recherche !== '' && normaliser(item.textContent).indexOf(recherche) === -1;
				});
			});
		});

		// Ajout d'une personne au champ, sans écraser celles déjà saisies.
		var picker = document.getElementById('people-picker');
		var champPersonnes = document.getElementById('c-people');
		function ajouterPersonne() {
			var nom = picker.value.trim();
			if (!nom) { return; }
			var existantes = champPersonnes.value.split(', ').filter(function (p) { return p.trim() !== ''; });
			if (existantes.indexOf(nom) === -1) {
				existantes.push(nom);
				champPersonnes.value = existantes.join(', ');
			}
			picker.value = '';
			picker.focus();
		}
		document.getElementById('people-add').addEventListener('click', ajouterPersonne);
		picker.addEventListener('keydown', function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				ajouterPersonne();
			}
		});

		// Ctrl + S enregistre le volet visible.
		document.addEventListener('keydown', function (e) {
			if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
				var volet = document.querySelector('.pane:not([hidden])');
				if (volet) {
					e.preventDefault();
					volet.querySelector('.editor').submit();
				}
			}
		});

		// L'élément en cours d'édition est amené sous les yeux.
		var courant = document.querySelector('.item.current');
		if (courant) {
			courant.scrollIntoView({ block: 'center' });
		}
	</script>
</body>
</html>
