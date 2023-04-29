<?php

class DBMANAGEEXT {

	private $mysqlix;
	
	public function announce() {
		return '
		<!-- database manage extension -->
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_shows" style="color:red;" onclick="return confirm(\'This will permanently delete all show data older than 10 days. Are you sure?\')">Trim Shows Played Log</a> | <a href="/?db_manage_ext=1&amp;action=trim_shows_empty" style="color:red;" onclick="return confirm(\'This will permanently delete all shows data. Are you sure?\')">[EMPTY]</a></li>		
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_commercials" style="color:red;" onclick="return confirm(\'This will permanently delete all commercials data older than 10 days. Are you sure?\')">Trim Commercials Log</a> | <a href="/?db_manage_ext=1&amp;action=trim_commercials_empty" style="color:red;" onclick="return confirm(\'This will permanently delete all commercials data. Are you sure?\')">[EMPTY]</a></li>
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_errors" style="color:red;" onclick="return confirm(\'This will permanently delete all error data older than 10 days. Are you sure?\')">Trim Errors Log</a> | <a href="/?db_manage_ext=1&amp;action=trim_errors_empty" style="color:red;" onclick="return confirm(\'This will permanently delete all error data. Are you sure?\')">[EMPTY]</a></li>
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_special" style="color:red;" onclick="return confirm(\'This will permanently delete all content with /specials/ in its path. Are you sure?\')">Expunge Specials</a></li>
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_cartoons" style="color:red;" onclick="return confirm(\'This will permanently delete all shows with /cartoons/ in its path. Are you sure?\')">Expunge Cartoons</a></li>
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_reruns" style="color:red;" onclick="return confirm(\'This will permanently delete all shows with /reruns/ in its path. Are you sure?\')">Expunge Reruns</a></li>
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_channels" style="color:red;" onclick="return confirm(\'This will permanently delete all content with /channels/ in its path. Are you sure?\')">Expunge Channels</a></li>
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_movies" style="color:red;" onclick="return confirm(\'This will permanently delete all content with /movies/ in its path. Are you sure?\')">Expunge Movies</a></li>
		<!-- end database manage extension -->
		';
	}
	
	public function load($database_info_host, $database_info_username, $database_info_password, $database_info_database_name) {
		$this->mysqlix = new mysqli($database_info_host, $database_info_username, $database_info_password, $database_info_database_name);
	}

	public function handle($args) {	
		switch($args["action"]) {
			case "trim_shows_empty":
				$res = $this->mysqlix->query("TRUNCATE TABLE `played`") or die($mysqli->error);
				return; //"empty";
			break;
			case "trim_shows":
				$res = $this->mysqlix->query("DELETE FROM played WHERE played<=" . (time()-864000) . " ORDER BY id DESC") or die($mysqli->error);
				return; //"trim";
			break;
			case "trim_commercials_empty":
				$res = $this->mysqlix->query("TRUNCATE TABLE `commercials`") or die($mysqli->error);
				return; //"com empty";
			break;
			case "trim_commercials":
				$res = $this->mysqlix->query("DELETE FROM commercials WHERE played<=" . (time()-864000) . " ORDER BY id DESC") or die($mysqli->error);
				return; //"com trim";
			break;
			case "trim_errors_empty":
				$res = $this->mysqlix->query("TRUNCATE TABLE `errors`") or die($mysqli->error);
				return; //"er empty";
			break;
			case "trim_errors":
				$res = $this->mysqlix->query("DELETE FROM errors WHERE played<=" . (time()-864000) . " ORDER BY id DESC") or die($mysqli->error);
				return; //"er trim";
			break;
			case "trim_special":
				$res = $this->mysqlix->query("DELETE FROM `played` WHERE INSTR(name, '/specials/')") or die($mysqli->error);
				return; //"er trim";
			break;
			case "trim_cartoons":
				$res = $this->mysqlix->query("DELETE FROM `played` WHERE INSTR(name, '/cartoons/')") or die($mysqli->error);
				return;
			break;
			case "trim_reruns":
				$res = $this->mysqlix->query("DELETE FROM `played` WHERE INSTR(name, '/reruns/')") or die($mysqli->error);
				return;
			break;
			case "trim_channels":
				$res = $this->mysqlix->query("DELETE FROM `played` WHERE INSTR(name, '/channels/')") or die($mysqli->error);
				return;
			break;
			case "trim_movies":
				$res = $this->mysqlix->query("DELETE FROM `played` WHERE INSTR(name, '/movies/')") or die($mysqli->error);
				return;
			break;
			case "trim_test_now":
				return "test";
			break;
		}
		return "Error Managing Database!";
	}
}

?>