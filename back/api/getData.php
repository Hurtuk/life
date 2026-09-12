<?php
	include '../php/datamodel.php';
	
	$type = $_GET['type'];
	
	$unclassified = $type == 'unclassified';
	$love = $type == 'love';

    global $db;
	
	if (!$unclassified) {
		if ($love) {
			function calculateAge($birthday, $date) {
				$birthdayTimestamp = strtotime($birthday);
				$dateTimestamp = strtotime($date);
				$age = date('Y', $dateTimestamp) - date('Y', $birthdayTimestamp);
				if (date('md', $dateTimestamp) < date('md', $birthdayTimestamp)) {
					$age--;
				}
				return $age;
			}
			
			$ranges = $db->select("
				SELECT lv.id, CONCAT(p.firstname, ' ', p.lastname) AS title,
					lv.comment, lv.startDate, lv.endDate, lv.color, lv.photoYear,
					CONCAT('http://".$_SERVER['HTTP_HOST']."/lifeBO/images/loves/', lv.id, '.jpg') AS icon, p.birthday
				FROM love_stories lv
				INNER JOIN people p
				ON p.id = lv.idPerson
				ORDER BY lv.startDate, lv.endDate DESC");
				
			// Ages
			foreach ($ranges as &$r) {
				$r['hisAge'] = calculateAge('1991-10-04', $r['startDate']);
				$r['herAge'] = calculateAge($r['birthday'], $r['startDate']);
			}
		} else {
			$ranges = $db->select("
				SELECT a.id, a.title, a.comment, a.startDate, a.endDate, s.title AS structure,
						a.role, a.type, CONCAT(t.name, '|http://".$_SERVER['HTTP_HOST']."/lifeBO/images/tags/', t.icon, '.png') AS tag, s.color,
						CONCAT('http://".$_SERVER['HTTP_HOST']."/lifeBO/images/structures/', s.id, '.jpg') AS icon
				FROM activities a
				LEFT JOIN tags t
				ON t.id = a.idTag
				INNER JOIN structures s
				ON s.id = a.idStructure
				".(isset($type) ? "WHERE type LIKE '$type'" : "")."
				ORDER BY a.startDate, a.endDate DESC");
		}
		foreach ($ranges as &$r) {
			$data = explode('|', $r['tag']);
			$r['tag'] = array(
				'name' => $data[0],
				'icon' => $data[1]
			);
		}
			
		$rangeIds = join(",", array_map(function($e) { return $e['id']; }, $ranges));
		
		$idAssociation = $love ? 'c.idLoveStory' : 'c.idActivity';
	} else {
		$idAssociation = 0;
	}
	
	$cond = "WHERE c.id";
	if ($unclassified) {
		$cond .= "LoveStory IS NULL AND c.idActivity IS NULL";
	} else if ($love) {
		$cond .= "LoveStory IN ($rangeIds)";
	} else {
		$cond .= "Activity IN ($rangeIds)";
	}

    $chapters = $db->select("
		SELECT c.id, c.title, c.content, c.startDate, c.endDate, c.narrated, $idAssociation AS idAssociation,
			GROUP_CONCAT(DISTINCT CONCAT(p.id, ':', p.firstname, ' ', p.lastname) ORDER BY p.lastname, p.firstname SEPARATOR ';') AS people,
			GROUP_CONCAT(DISTINCT CONCAT(t.name, '|http://".$_SERVER['HTTP_HOST']."/lifeBO/images/tags/', t.icon, '.png') ORDER BY ct.priority SEPARATOR ',') AS tags
		FROM chapters c
		LEFT JOIN (chapter_people cp
			INNER JOIN people p
			ON p.id = cp.idPerson)
		ON cp.idChapter = c.id
		LEFT JOIN (chapter_tags ct
			INNER JOIN tags t
			ON t.id = ct.idTag)
		ON ct.idChapter = c.id
		$cond
		GROUP BY c.id
		ORDER BY c.startDate, c.endDate");
		
	foreach ($chapters as &$c) {
		$c['narrated'] = $c['narrated'] == "1";
		$c['tags'] = explode(',', $c['tags']);
		foreach ($c['tags'] as &$i) {
			$data = explode('|', $i);
			$i = array(
				'name' => $data[0],
				'icon' => $data[1]
			);
		}
		if ($c['people']) {
			$c['people'] = explode(';', $c['people']);
			foreach ($c['people'] as &$i) {
				$data = explode(':', $i);
				$i = array(
					'id' => $data[0],
					'name' => $data[1]
				);
			}
		}
	}

    echoResult(array(
		'ranges' => $ranges ?? [],
		'chapters' => $chapters ?? []
	));
?>