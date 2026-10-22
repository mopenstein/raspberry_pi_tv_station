<?php

class Settings {

	public function json_error_to_string($id) {
		switch ($id) {
		  case JSON_ERROR_NONE:
			return "No errors";
			break;
		  case JSON_ERROR_DEPTH:
			return "Maximum stack depth exceeded";
			break;
		  case JSON_ERROR_STATE_MISMATCH:
			return "Invalid or malformed JSON";
			break;
		  case JSON_ERROR_CTRL_CHAR:
			return "Control character error";
			break;
		  case JSON_ERROR_SYNTAX:
			return "Syntax error";
			break;
		  case JSON_ERROR_UTF8:
			return "Malformed UTF-8 characters";
			break;
		  default:
			return "Unknown error";
			break;
		}
		return "Unknown error";
	}

	public function json_validator($data=NULL) {
	  if (!empty($data)) {
			@json_decode($data);
			return [(json_last_error() === JSON_ERROR_NONE), $this->json_error_to_string(json_last_error())];
		}
		return false;
	}

	function load($settingsFileLocation) {	
		$json_data = file_get_contents($settingsFileLocation);
		$json_valid = $this->json_validator($json_data);
		
		if(!$json_valid[0]) {
			return [null, $json_valid[1]];
		}

		return [json_decode($json_data, true)];
	}
}
?>