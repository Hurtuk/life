<?php
	include '../php/datamodel.php';
	
	$type = $_GET['type'];
	$id = $_GET['id'];
	
	$love = $type == 'love';
	$activity = $type == 'range';
	$chapter = $type == 'chapter';

    global $db;
	
	if ($love) {
		$ranges = $db->select("
			SELECT lv.id, CONCAT(p.firstname, ' ', p.lastname) AS title, lv.comment, lv.startDate, lv.endDate, lv.color, p.birthday, CONCAT('http://".$_SERVER['HTTP_HOST']."/lifeBO/images/loves/', lv.id, '.jpg') AS icon
			FROM love_stories lv
			INNER JOIN people p
			ON p.id = lv.idPerson
			ORDER BY lv.startDate, lv.endDate DESC");
	} else {
		$ranges = $db->select("
			SELECT a.id, a.title, a.comment, a.startDate, a.endDate, s.title AS structure, a.role, a.type, t.name, s.color, p.birthday, CONCAT('http://".$_SERVER['HTTP_HOST']."/lifeBO/images/structures/', s.id, '.jpg') AS icon
			FROM activities a
			LEFT JOIN tags t
			ON t.id = a.idTag
			INNER JOIN structures s
			ON s.id = a.idStructure
			".(isset($type) ? "WHERE type LIKE '$type'" : "")."
			ORDER BY a.startDate, a.endDate DESC");
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
			GROUP_CONCAT(DISTINCT CONCAT(p.id, ':', p.firstname, p.lastname) SEPARATOR ';') AS people,
			GROUP_CONCAT(DISTINCT CONCAT(t.id, ':', t.name) SEPARATOR ';') AS tags,
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
		$c['tags'] = explode(',', $c['tags']);
		foreach ($c['tags'] as &$i) {
			$data = explode('|', $i);
			$i = array(
				'name' => $data[0],
				'icon' => $data[1]
			);
		}
	}

    echoResult(array(
		'ranges' => $ranges ?? [],
		'chapters' => $chapters ?? []
	));
?>