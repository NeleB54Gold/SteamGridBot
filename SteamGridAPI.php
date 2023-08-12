<?php

class SteamGridAPI {
	# API Endpoint [Default v2]
	public $endpoint = 'https://www.steamgriddb.com/api/v2';
	# API Token
	private $token = 'API_TOKEN';
	# Database class
	private $db = [];
	# Cache time
	public $cache_time = 60 * 60 * 2;
	# Request timeout
	public $timeout = 5;
	# Default API language (Not supported by API)
	public $lang = 'en';
	# Supported languages by API
	private $langs = ['en', 'it', 'pt'];
	# Libraries
	private $libs = [
		"steam",	// Steam
		"origin",	// Origin
		"egs",		// Epic Games
		"bnet",		// Battle Net
		"uplay",	// uPlay
		"flashpoint",// Flashpoint Archive
		"eshop"		// Nintendo eShop
	];
	
	# Set configs
	public function __construct ($db = [], $lang = 'en') {
		if (in_array($lang, $this->langs)) $this->lang = $lang;
		if (is_a($db, 'Database') && $db->configs['redis']['status']) $this->db = $db;
		$this->header = [
			'Authorization: Bearer ' . $this->token
		];
	}
	
	# Custom API requests
	public function request (string $method = '', array $args = [], int $timeout = null) {
		if (!isset($this->curl))	$this->curl = curl_init();
		$url = $this->endpoint . '/' . $method . '?' . http_build_query($args);
		if (is_null($timeout)) $timeout = $this->timeout;
		if (is_a($this->db, 'Database')) {
			$cache = $this->db->rget($url);
			if ($r = json_decode($cache, 1)) return $r;
		}
		curl_setopt_array($this->curl, [
			CURLOPT_URL				=> $url,
			CURLOPT_SSL_VERIFYHOST	=> false,
			CURLOPT_SSL_VERIFYPEER	=> false,
			CURLOPT_HTTPHEADER		=> $this->header,
			CURLOPT_HTTPGET			=> true,
			CURLOPT_TIMEOUT			=> $timeout,
			CURLOPT_RETURNTRANSFER	=> true
		]);
		$output = curl_exec($this->curl);
		if ($json_output = json_decode($output, true)) {
			if (is_a($db, 'Database') && $this->db->configs['redis']['status']) $this->db->rset($url, json_encode($json_output), $this->cache_time);
			$json_output['url'] = $url;
			return $json_output;
		}
		if ($output) return [$output];
		if ($error = curl_error($this->curl)) return ['ok' => false, 'error_code' => 500, 'description' => 'CURL Error: ' . $error, 'url' => $url];
		if (!$output) return ['ok' => false, 'error_code' => 500, 'description' => curl_getinfo($this->curl), 'url' => $url];
	}
	
	# Retrieve game by game ID.
	public function getGameByID ($gameId) {
		return $this->request('games/id/' . $gameId);
	}
	
	# Retrieve game by Steam App ID.
	public function getGameBySteamID ($steamAppId, $oneoftag = null) {
		$args = [];
		if (!is_null($oneoftag)) $args['oneoftag'] = $oneoftag;
		return $this->request('games/steam/' . $steamAppId, $args);
	}
	
	# Retrieve grids by game ID.
	public function getGrids ($gameId, $page = 0, $oneoftag = null) {
		$args['types'] = 'animated,static';
		$args['humor'] = $args['epilepsy'] = $args['oneoftag'] = 'any';
		if (!is_null($oneoftag)) $args['oneoftag'] = $oneoftag;
		$args['page'] = $page;
		return $this->request('grids/game/' . $gameId, $args);
	}
	
	# Retrieve heroes by game ID.
	public function getHeroes ($gameId, $page = 0, $oneoftag = null) {
		$args['types'] = 'animated,static';
		$args['humor'] = $args['epilepsy'] = $args['oneoftag'] = 'any';
		if (!is_null($oneoftag)) $args['oneoftag'] = $oneoftag;
		$args['page'] = $page;
		return $this->request('heroes/game/' . $gameId, $args);
	}
	
	# Retrieve logos by game ID.
	public function getLogos ($gameId, $page = 0, $oneoftag = null) {
		$args['types'] = 'animated,static';
		$args['humor'] = $args['epilepsy'] = $args['oneoftag'] = 'any';
		if (!is_null($oneoftag)) $args['oneoftag'] = $oneoftag;
		$args['page'] = $page;
		return $this->request('logos/game/' . $gameId, $args);
	}
	
	# Retrieve icons by game ID.
	public function getIcons ($gameId, $page = 0, $oneoftag = null) {
		$args['types'] = 'animated,static';
		$args['humor'] = $args['epilepsy'] = $args['oneoftag'] = 'any';
		if (!is_null($oneoftag)) $args['oneoftag'] = $oneoftag;
		$args['page'] = $page;
		return $this->request('icons/game/' . $gameId, $args);
	}
	
	# Search for a game by name
	public function getGamesByName ($term) {
		return $this->request('search/autocomplete/' . $term);
	}
}

?>