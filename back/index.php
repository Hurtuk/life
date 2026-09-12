<?php
	include './php/datamodel.php';

	global $db;
	
	if (isset($_POST['editActivity'])) {
		$activity = $db->selectVal("SELECT id, title, idStructure, role, type, idTag, startDate, endDate, comment FROM activities WHERE id = ?", array($_POST['editActivity']));
	} else if (isset($_POST['editChapter'])) {
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
										GROUP BY c.id", array($_POST['editChapter']));
		$chapter['narrated'] = $chapter['narrated'] == "1";
		$chapter['tags'] = explode(',', $chapter['tags']);
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
			} else {
				$args[] = $_POST['id'];
				$db->update("UPDATE activities SET title = ?, idStructure = ?, role = ?, type = ?, idTag = ?, startDate = ?, endDate = ?, comment = ? WHERE id = ?", $args);
			}
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
			foreach ($_POST['tags'] as $t) {
				if ($t) {
					$db->update("INSERT INTO chapter_tags (idChapter, idTag, priority) VALUES (?, ?, ?)", array($id, $t, $i++));
				}
			}
		}
	}

?>
<!doctype html>
<html>
<head>
	<title>Life BO</title>
    <meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style>
		label { display: block; }
		input[type=checkbox] + form { display: none; }
		input[type=checkbox]:checked + form { display: initial; }
		input[type=text], textarea { width: 400px; }
	</style>
</head>
<body>
	<form method="post" action="">
		Editer activité : <select name="editActivity"><?php
			$acts = $db->select("SELECT id, startDate, endDate, title, comment FROM activities ORDER BY startDate DESC");
			foreach ($acts as $a) {
				?><option value="<?= $a['id'] ?>"><?= (!$a['comment'] ? '******** ' : '').$a['startDate'].' - '.$a['endDate'].' : '.$a['title'] ?></option><?php
			}
		?></select>
		<input type="submit" value="Editer" />
	</form>
	<form method="post" action="">
		Editer chapitre : <select name="editChapter"><?php
			$chaps = $db->select("SELECT id, startDate, title, narrated FROM chapters ORDER BY startDate DESC");
			foreach ($chaps as $c) {
				?><option value="<?= $c['id'] ?>"><?= ($c['narrated'] == "0" ? '******** ' : '').$c['startDate'].' : '.$c['title'] ?></option><?php
			}
		?></select>
		<input type="submit" value="Editer" />
	</form>
	<fieldset>
		<legend>Editer activité</legend>
		<input type="checkbox" <?= $activity ? 'checked' : '' ?> />
		<form method="post" action="">
			<input type="hidden" name="element" value="activity" />
			<input type="hidden" name="id" value="<?= $activity ? $activity['id'] : -1 ?>" />
			<label>Title : <input type="text" name="title" value="<?= $activity['title'] ?>" /></label>
			<label>Structure : <select name="structure"><?php
					$ss = $db->select("SELECT id, title FROM structures ORDER BY title");
					foreach ($ss as $s) {
						?><option value="<?= $s['id'] ?>"<?= $activity['idStructure'] == $s['id'] ? ' selected' : '' ?>><?= $s['title'] ?></option><?php
					}
				?></select></label>
			<label>Rôle : <input type="text" name="role" value="<?= $activity['role'] ?>" /></label>
			<label>Type : <select name="type">
				<option<?= $activity['type'] == 'school' ? ' selected' : '' ?>>school</option>
				<option<?= $activity['type'] == 'job' ? ' selected' : '' ?>>job</option>
				<option<?= $activity['type'] == 'hobby' ? ' selected' : '' ?>>hobby</option>
				<option<?= $activity['type'] == 'volunteering' ? ' selected' : '' ?>>volunteering</option>
			</select></label>
			<label>Tag : <select name="tag"><option></option><?php
					$ss = $db->select("SELECT id, name FROM tags ORDER BY name");
					foreach ($ss as $s) {
						?><option value="<?= $s['id'] ?>"<?= $activity['idTag'] == $s['id'] ? ' selected' : '' ?>><?= $s['name'] ?></option><?php
					}
				?></select></label>
			<label>StartDate : <input type="date" name="startDate" value="<?= $activity['startDate'] ?>" /></label>
			<label>EndDate : <input type="date" name="endDate" value="<?= $activity['endDate'] ?>" /></label>
			<label>Comment : <textarea name="comment"><?= $activity['comment'] ?></textarea></label>
			<input type="submit" value="Enregistrer" />
		</form>
	</fieldset>
	<fieldset>
		<legend>Editer chapitre</legend>
		<input type="checkbox" <?= $chapter ? 'checked' : '' ?> />
		<form method="post" action="">
			<input type="hidden" name="element" value="chapter" />
			<input type="hidden" name="id" value="<?= $chapter ? $chapter['id'] : -1 ?>" />
			<label>Title : <input type="text" name="title" value="<?= $chapter['title'] ?>" /></label>
			<label>StartDate : <input type="date" name="startDate" value="<?= $chapter['startDate'] ?>" /></label>
			<label>EndDate : <input type="date" name="endDate" value="<?= $chapter['endDate'] ?>" /></label>
			<label>Rédigé ? <input type="checkbox" name="narrated" <?= $chapter['narrated'] ? 'checked' : '' ?>/></label>
			<label>Activité : <select name="activity"><option></option><?php
					$acts = $db->select("SELECT id, startDate, endDate, title FROM activities ORDER BY startDate DESC");
					foreach ($acts as $a) {
						?><option value="<?= $a['id'] ?>"<?= $chapter['idActivity'] == $a['id'] ? ' selected' : '' ?>><?= $a['startDate'].' - '.$a['endDate'].' : '.$a['title'] ?></option><?php
					}
				?></select></label>
			<label>Love story : <select name="loveStory"><option></option><?php
					$acts = $db->select("SELECT ls.id, startDate, endDate, CONCAT(firstname, ' ', lastname) AS title FROM love_stories ls INNER JOIN people p ON p.id = idPerson ORDER BY startDate DESC");
					foreach ($acts as $a) {
						?><option value="<?= $a['id'] ?>"<?= $chapter['idLoveStory'] == $a['id'] ? ' selected' : '' ?>><?= $a['startDate'].' - '.$a['endDate'].' : '.$a['title'] ?></option><?php
					}
				?></select></label>
			<label>Personnes : <input type="text" name="people" value="<?= $chapter['people'] ?>" /></label>
			<label>Content : <textarea name="content"><?= $chapter['content'] ?></textarea></label>
			<fieldset>
				<legend>Tags</legend>
				<?php $tags = $db->select("SELECT id, name FROM tags ORDER BY name"); ?>
				<?php
					for ($i = 0; $i < 5; $i++) {
						?><label>Label <?= $i ?> : <select name="tags[]"><option></option><?php
							foreach ($tags as $t) {
								?><option value="<?= $t['id'] ?>"<?= $chapter['tags'][$i] == $t['id'] ? ' selected' : '' ?>><?= $t['name'] ?></option><?php
							}
						?></select></label><?php
					}
				?>
			</fieldset>
			<input type="submit" value="Enregistrer" />
		</form>
	</fieldset>
</body>
</html>