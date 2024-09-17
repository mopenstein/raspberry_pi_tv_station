<?php

class DBMANAGEEXT {

	private $mysqlix;
	
	public function announce() {
		return '
		<!-- database manage extension -->
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_shows" style="color:red;" onclick="return confirm(\'This will permanently delete all show data older than 10 days. Are you sure?\')">Trim Shows Played Log</a> | <a href="/?db_manage_ext=1&amp;action=trim_shows_empty" style="color:red;" onclick="return confirm(\'This will permanently delete all shows data. Are you sure?\')">[EMPTY]</a></li>		
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_commercials" style="color:red;" onclick="return confirm(\'This will permanently delete all commercials data older than 10 days. Are you sure?\')">Trim Commercials Log</a> | <a href="/?db_manage_ext=1&amp;action=trim_commercials_empty" style="color:red;" onclick="return confirm(\'This will permanently delete all commercials data. Are you sure?\')">[EMPTY]</a></li>
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_errors" style="color:red;" onclick="return confirm(\'This will permanently delete all error data older than 10 days. Are you sure?\')">Trim Messages Log</a> | <a href="/?db_manage_ext=1&amp;action=trim_errors_empty" style="color:red;" onclick="return confirm(\'This will permanently delete all messages. Are you sure?\')">[EMPTY]</a></li>
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_shorts" style="color:red;" onclick="return confirm(\'This will permanently delete all content with /shorts/ in its path. Are you sure?\')">Expunge Shorts</a></li>
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_special" style="color:red;" onclick="return confirm(\'This will permanently delete all content with /specials/ in its path. Are you sure?\')">Expunge Specials</a></li>
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_cartoons" style="color:red;" onclick="return confirm(\'This will permanently delete all shows with /cartoons/ in its path. Are you sure?\')">Expunge Cartoons</a></li>
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_reruns" style="color:red;" onclick="return confirm(\'This will permanently delete all shows with /reruns/ in its path. Are you sure?\')">Expunge Reruns</a></li>
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_channels" style="color:red;" onclick="return confirm(\'This will permanently delete all content with /channels/ in its path. Are you sure?\')">Expunge Channels</a></li>
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=trim_movies" style="color:red;" onclick="return confirm(\'This will permanently delete all content with /movies/ in its path. Are you sure?\')">Expunge Movies</a></li>
			<li style="padding:4px;"><a href="/?db_manage_ext=1&amp;action=all_but_prime" style="color:red;" onclick="return confirm(\'This will permanently delete all entries EXCEPT those with /primetime/, /cartoons, and /reruns/ in its path. Are you sure?\')">Delete ALL but PRIMETIME, CARTOONS, and RERUNS</a></li>
			<br />
			<div>
			Insert show into database:<br />
			<form method="GET">
			<ul>
			Short Name: <input type="text" name="insert" /><br />
			File Name: <input type="text" name="file" /> <small>*must be full path to file</small><br />
			How many times? <input type="number" name="times" value="10" /><br />
			<input type="submit" />
			</ul>
			</div>
		<!-- end database manage extension -->
		';
	}
	
	public function load($database_info_host, $database_info_username, $database_info_password, $database_info_database_name) {
		$this->mysqlix = new mysqli($database_info_host, $database_info_username, $database_info_password, $database_info_database_name);
	}

	public function handle($args) {	
		switch($args["action"]) {
			case "trim_shows_empty":
				$res = $this->mysqlix->query("TRUNCATE TABLE `played`") or die($this->mysqlix->error);
				return; //"empty";
			break;
			case "trim_shows":
				$res = $this->mysqlix->query("DELETE FROM played WHERE played<=" . (time()-864000) . " ORDER BY id DESC") or die($this->mysqlix->error);
				return; //"trim";
			break;
			case "trim_commercials_empty":
				$res = $this->mysqlix->query("TRUNCATE TABLE `commercials`") or die($this->mysqlix->error);
				return; //"com empty";
			break;
			case "trim_commercials":
				$res = $this->mysqlix->query("DELETE FROM commercials WHERE played<=" . (time()-864000) . " ORDER BY id DESC") or die($this->mysqlix->error);
				return; //"com trim";
			break;
			case "trim_errors_empty":
				$res = $this->mysqlix->query("TRUNCATE TABLE `errors`") or die($this->mysqlix->error);
				return; //"er empty";
			break;
			case "trim_errors":
				$res = $this->mysqlix->query("DELETE FROM errors WHERE played<=" . (time()-864000) . " ORDER BY id DESC") or die($this->mysqlix->error);
				return; //"er trim";
			break;
			case "trim_shorts":
				$res = $this->mysqlix->query("DELETE FROM `played` WHERE INSTR(name, '/shorts/')") or die($this->mysqlix->error);
				return; //"er trim";
			break;
			case "trim_special":
				$res = $this->mysqlix->query("DELETE FROM `played` WHERE INSTR(name, '/specials/')") or die($this->mysqlix->error);
				return; //"er trim";
			break;
			case "trim_cartoons":
				$res = $this->mysqlix->query("DELETE FROM `played` WHERE INSTR(name, '/cartoons/')") or die($this->mysqlix->error);
				return;
			break;
			case "trim_reruns":
				$res = $this->mysqlix->query("DELETE FROM `played` WHERE INSTR(name, '/reruns/')") or die($this->mysqlix->error);
				return;
			break;
			case "trim_channels":
				$res = $this->mysqlix->query("DELETE FROM `played` WHERE INSTR(name, '/channels/')") or die($this->mysqlix->error);
				return;
			break;
			case "trim_movies":
				$res = $this->mysqlix->query("DELETE FROM `played` WHERE INSTR(name, '/movies/')") or die($this->mysqlix->error);
				return;
			break;
			case "all_but_prime":
				$res = $this->mysqlix->query("DELETE FROM `played` WHERE INSTR(name, '/primetime/') = 0 AND INSTR(name, '/cartoons/') = 0 AND INSTR(name, '/reruns/') = 0 AND INSTR(name, '/specials/') = 0") or die($this->mysqlix->error);
				$res = $this->mysqlix->query("DELETE FROM `played` WHERE INSTR(name, '/primetime/random/')") or die($this->mysqlix->error);
				$res = $this->mysqlix->query("DELETE FROM `played` WHERE INSTR(name, '/thanksgiving/')") or die($this->mysqlix->error);
				return;
			break;
			case "trim_test_now":
				$res = $this->mysqlix->query("SELECT name FROM `played` WHERE INSTR(name, '/primetime/') = 0 AND INSTR(name, '/cartoons/') = 0 AND INSTR(name, '/reruns/') = 0") or die($this->mysqlix->error);
				while ($brow = $res->fetch_assoc()) {
					var_dump($brow);
				}
				return;
			break;
		}
		return "Error Managing Database!";
	}
}

?>