<?php

# Ignore
if ($v->via_bot || $v->update['edited_message']) die;

# Start SteamGrid API class
$sg = new SteamGridAPI();
$bot->username = 'SteamGridBot';

# Private chat with Bot
if ($v->chat_type == 'private' || $v->inline_message_id) {
	if ($bot->configs['database']['status'] && $user['status'] !== 'started') $db->setStatus($v->user_id, 'started');
	
	# Start message
	if (in_array('start', [$v->command, $v->query_data])) {
		$t = $bot->bold('Games Grids Bot') . PHP_EOL . $bot->italic($tr->getTranslation('startMessage'), 1);
		$buttons[] = [
			$bot->createInlineButton($tr->getTranslation('aboutButton'), 'about'),
			$bot->createInlineButton($tr->getTranslation('helpButton'), 'help')
		];
		$buttons[][] = $bot->createInlineButton($tr->getTranslation('changeLanguage'), 'lang');
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		}
	}
	# About message
	elseif ($v->command == 'about' || $v->query_data == 'about') {
		$buttons[][] = $bot->createInlineButton('◀️', 'start');
		$t = $tr->getTranslation('aboutMessage', [explode('-', phpversion(), 2)[0]]);
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		}
	}
	# Change language
	elseif ($v->command == 'lang' || $v->query_data == 'lang' || strpos($v->query_data, 'changeLanguage-') === 0) {
		$langnames = [
			'en' => '🇬🇧 English',
			'it' => '🇮🇹 Italiano'
		];
		if (strpos($v->query_data, 'changeLanguage-') === 0) {
			$select = str_replace('changeLanguage-', '', $v->query_data);
			if (in_array($select, array_keys($langnames))) {
				$tr->setLanguage($select);
				$user['lang'] = $select;
				$db->query('UPDATE users SET lang = ? WHERE id = ?', [$user['lang'], $user['id']]);
			}
		}
		$langnames[$user['lang']] .= ' ✅';
		$t = '🔡 Select your language';
		$formenu = 2;
		$mcount = 0;
		foreach ($langnames as $lang_code => $name) {
			if (isset($buttons[$mcount]) && count($buttons[$mcount]) >= $formenu) $mcount += 1;
			$buttons[$mcount][] = $bot->createInlineButton($name, 'changeLanguage-' . $lang_code);
		}
		$buttons[][] = $bot->createInlineButton('◀️', 'start');
		if ($v->query_id) {
			$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
			$bot->answerCBQ($v->query_id);
		} else {
			$bot->sendMessage($v->chat_id, $t, $buttons);
		}
	}
	# Download photo callback
	elseif (strpos($v->query_data, 'dlpack_') === 0) {
		$rid = 'SGRD-download-' . $v->user_id;
		if ($db->rget($rid)) {
			$bot->answerCBQ($v->query_id, $tr->getTranslation('requestPending'), true);
			die;
		} else {
			# Timeout of 10 minutes if something fail
			$db->rset($rid, true, 60 * 10);
		}
		$bot->answerCBQ($v->query_id, '👌');
		$id = str_replace('dlpack_', '', $v->query_data);
		$game = $sg->getGameByID($id);
		if ($game['success']) {
			if ($game['data']) {
				$game = $game['data'];
				$games_dir = __DIR__ . '/../games';
				$game_dir = __DIR__ . '/../games/' . $game['id'];
				$upload_dir = 'https://' . $_SERVER['SERVER_NAME'] . str_replace('index.php', '', $_SERVER['SCRIPT_NAME']) . '/games/' . $game['id'] . '/';
				if (!is_dir($games_dir)) mkdir($games_dir);
				if (!is_dir($game_dir)) mkdir($game_dir);
				$grids = $sg->getGrids($id);
				$heroes = $sg->getHeroes($id);
				$logos = $sg->getLogos($id);
				$icons = $sg->getIcons($id);
				$docs = [];
				if ($grids['success'] && $grids['data']) {
					copy($grids['data'][0]['url'], $game_dir . '/grid.png');
					$docs[] = $bot->createDocumentInput($upload_dir . '/grid.png');
				}
				if ($heroes['success'] && $heroes['data']) {
					copy($heroes['data'][0]['url'], $game_dir . '/hero.png');
					$docs[] = $bot->createDocumentInput($upload_dir . '/hero.png');
				}
				if ($logos['success'] && $logos['data']) {
					copy($logos['data'][0]['url'], $game_dir . '/logo.png');
					$docs[] = $bot->createDocumentInput($upload_dir . '/logo.png');
				}
				if ($icons['success'] && $icons['data']) {
					copy($icons['data'][0]['url'], $game_dir . '/icon.png');
					$docs[] = $bot->createDocumentInput($upload_dir . '/icon.png');
				}
				$bot->configs['response'] = true;
				$sent = $bot->sendMediaGroup($v->chat_id, $docs);
				if (!$sent['ok']) {
					$bot->sendMessage($v->chat_id, $tr->getTranslation('instanceError'));
				}
			}
		}
		$db->rdel($rid);
	}
	# Download photo callback
	elseif (strpos($v->query_data, 'dl_') === 0) {
		$bot->answerCBQ($v->query_id, false, false, 'https://t.me/' . $bot->username . '?start=' . $v->query_data);
	}
	# Download photo
	elseif (strpos($v->command, 'start dl_') === 0) {
		$id = explode('_', $v->command);
		$type = substr($id[1], 0, 1);
		$gameId = substr($id[1], 1);
		if ($type == 1) {
			$photos = $sg->getGrids($gameId);
		} elseif ($type == 2) {
			$photos = $sg->getHeroes($gameId);
		} elseif ($type == 3) {
			$photos = $sg->getLogos($gameId);
		} elseif ($type == 4) {
			$photos = $sg->getIcons($gameId);
		} else {
			$photos = [];
		}
		if ($photos['success']) {
			if ($photos['data']) {
				foreach ($photos['data'] as $photo) {
					if ($photo['id'] == $id[2]) $tphoto = $photo;
				}
				if (isset($tphoto['url'])) {
					$caption = $tr->getTranslation('photoInfo', [
						$tphoto['width'] . 'x' . $tphoto['height'],
						$tphoto['notes'] ? $tphoto['notes'] : '🕸',
						$tphoto['author']['steam64'],
						htmlspecialchars($tphoto['author']['name'])
					]);
					$bot->sendDocument($v->chat_id, $tphoto['url'], $caption);
					die;
				} else {
					$t = $tr->getTranslation('photoNotFound');
				}
			} else {
				$t = $tr->getTranslation('noGamesFound');
			}
		} else {
			$t = $tr->getTranslation('instanceError');
		}
		$bot->sendMessage($v->chat_id, $t);
	}
	# Get game info
	elseif (strpos($v->query_data, 'game_') === 0) {
		$id = str_replace('game_', '', $v->query_data);
		$bot->editText($v->chat_id, $v->message_id, '🧐');
		$bot->answerCBQ($v->query_id);
		$game = $sg->getGameByID($id);
		if ($game['success']) {
			if ($game['data']) {
				$game = $game['data'];
				$grids = $sg->getHeroes($id);
				if (isset($game['release_date'])) $release = ' (' . date('Y', $game['release_date']) . ')';
				$t = '🎮 ' . $bot->bold($game['name'], true) . $bot->italic($release, true);
				$buttons[] = [
					$bot->createInlineButton($tr->getTranslation('typeGrids'), 'grids: ' . $game['id'], 'switch_inline_query_current_chat'),
					$bot->createInlineButton($tr->getTranslation('typeHeroes'), 'heroes: ' . $game['id'], 'switch_inline_query_current_chat')
				];
				$buttons[] = [
					$bot->createInlineButton($tr->getTranslation('typeLogos'), 'logos: ' . $game['id'], 'switch_inline_query_current_chat'),
					$bot->createInlineButton($tr->getTranslation('typeIcons'), 'icons: ' . $game['id'], 'switch_inline_query_current_chat')
				];
				$buttons[][] = $bot->createInlineButton($tr->getTranslation('downloadButton'), 'dlpack_' . $game['id']);
				if ($grids['success'] && $grids['data']) {
					$bot->sendPhoto($v->chat_id, $grids['data'][0]['url'], $t, $buttons);
					$bot->deleteMessage($v->chat_id, $v->message_id);
					die;
				}
			} else {
				$t = $tr->getTranslation('noGamesFound');
			}
		} else {
			$t = $tr->getTranslation('instanceError');
		}
		$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
		$bot->answerCBQ($v->query_id);
	}
	# Search for games
	else {
		if ($v->text && !$v->command && !$v->query_id) {
			$games = $sg->getGamesByName($v->text);
			if ($games['success']) {
				if ($games['data']) {
					$t = $bot->italic($tr->getTranslation('chooseGame'));
					foreach ($games['data'] as $game) {
						if (isset($game['release_date'])) {
							$release = ' (' . date('Y', $game['release_date']) . ')';
						} else {
							$release = '';
						}
						$buttons[][] = $bot->createInlineButton(
							$game['name'] . $release,
							'game_' . $game['id']
						);
					}
				} else {
					$t = $tr->getTranslation('noGamesFound');
				}
			} else {
				$t = $tr->getTranslation('instanceError');
			}
			$bot->sendMessage($v->chat_id, $t, $buttons);
		} else {
			$buttons[][] = $bot->createInlineButton('◀️', 'start');
			$t = $tr->getTranslation('helpMessage');
			if ($v->query_id) {
				$bot->editText($v->chat_id, $v->message_id, $t, $buttons);
				$bot->answerCBQ($v->query_id);
			} else {
				$bot->sendMessage($v->chat_id, $t, $buttons);
			}
		}
		die;
	}
} 
# Unsupported chats (Auto-leave)
elseif (in_array($v->chat_type, ['group', 'supergroup', 'channels'])) {
	$bot->leave($v->chat_id);
	die;
}
elseif ($v->update['inline_query']) {
	$results = [];
	# Show all images
	if (strpos($v->query, 'grids: ') === 0 && is_numeric($id = str_replace('grids: ', '', $v->query))) {
		$r = $sg->getGrids($id, $v->offset);
		$type = 1;
	} elseif (strpos($v->query, 'heroes: ') === 0 && is_numeric($id = str_replace('heroes: ', '', $v->query))) {
		$r = $sg->getHeroes($id, $v->offset);
		$type = 2;
	} elseif (strpos($v->query, 'logos: ') === 0 && is_numeric($id = str_replace('logos: ', '', $v->query))) {
		$r = $sg->getLogos($id, $v->offset);
		$type = 3;
	} elseif (strpos($v->query, 'icons: ') === 0 && is_numeric($id = str_replace('icons: ', '', $v->query))) {
		$r = $sg->getIcons($id, $v->offset);
		$type = 4;
	}
	$id = $type . $id;
	if ($r['success'] && $r['data']) {
		foreach ($r['data'] as $photo) {
			$mime_type = explode('/', $photo['mime']);
			# Photos (pngg, jpg, jpeg)
			if ($mime_type[0] == 'image' && in_array($mime_type[1], ['png', 'jpg', 'jpeg'])) {
				$results[] = $bot->createInlinePhoto(
					$id . '-' .  $photo['id'],
					'',
					'',
					$photo['url'],
					$photo['notes'],
					'',
					[[$bot->createInlineButton($tr->getTranslation('downloadButton'), 'dl_' . $id . '_' .  $photo['id'])]],
					$photo['thumb']
				);
			}
			# Animated pictures (webp)
			elseif ($mime_type[0] == 'image' && $mime_type[1] == 'webp') {
				$results[] = $bot->createInlineGif(
					$id . '-' .  $photo['id'],
					$photo['id'],
					'',
					$photo['url'],
					$photo['notes'],
					'',
					[[$bot->createInlineButton($tr->getTranslation('downloadButton'), 'dl_' . $id . '_' .  $photo['id'])]],
					$photo['thumb']
				);
			}
			# Icons (use thumb instead of ico)
			elseif ($mime_type[0] == 'image' && $mime_type[1] == 'vnd.microsoft.icon') {
				$results[] = $bot->createInlinePhoto(
					$id . '-' .  $photo['id'],
					'',
					'',
					$photo['thumb'],
					$photo['notes'],
					'',
					[[$bot->createInlineButton($tr->getTranslation('downloadButton'), 'dl_' . $id . '_' .  $photo['id'])]],
					$photo['thumb']
				);
			}
			# Unknown format
			else {
				$bot->sendLog('Unknown mime type: ' . $photo['mime']);
			}
		}
		$next = (count($r['data']) == 50) ? $v->offset + 1 : false;
	} else {
		$next = false;
	}
	$bot->answerIQ($v->id, $results, false, false, $next);
}

?>