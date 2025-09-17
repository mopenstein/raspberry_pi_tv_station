#!/usr/bin/python

# version: 102.1.1
# version date: 2025.xx.xx
#
#	Fixed error bumper generation error
#
# settings version: 0.994
#
#

#!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!#
# 		Do not expose your Raspberry Pi directly to the internet via port forwarding or DMZ.	 #
# 		This software is designed for local network use only.									 #
# 		Opening it up to the web will ruin your day.											 #
#!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!#

from omxplayer import OMXPlayer # the video player
from time import sleep			# used to give time for the player to load the video file
import math						# math functions
import os						# for path directory access
import glob						# how we quickly get all files in a directory
import random					# choosing random stuff
import time						# time functions
import datetime					# date and time, used for testing purposes
import urllib2					# web stuff
import urllib					# web stuff
import re						# regular expressions
import calendar					# used in special date calculating
import sys						# for accepting arguments from command line
import json						# settings file is in json format
import subprocess				# for rebooting the machine
import traceback

# Constants

SETTINGS_VERSION = 0.994 # the version of the "settings" this script supports

GET_VIDEOS_FROM_DIR_MIN_DURATION = 0 	 	# default minimum duration of a video in seconds when returning videos from a directory
GET_VIDEOS_FROM_DIR_MAX_DURATION = 99999 	# default maximum duration of a video in seconds when returning videos from a directory
VIDEO_EXTENSIONS = ('mp4', 'avi', 'webm', 'mpeg', 'm4v', 'mkv', 'mov', 'flv', 'wmv')	# video file extensions that are considered valid video files

# /Constants

# json.loads class that allows references to itself
class ReferenceDecoder(json.JSONDecoder):
		def __init__(self, *args, **kwargs):
				super(ReferenceDecoder, self).__init__(object_hook=self.object_hook, *args, **kwargs)
				self.references = []

		def object_hook(self, obj):
				self.references.append(obj)
				for item in obj:
						if str(obj[item]).startswith("$ref/"):
								oref = str(obj[item]).split("/")
								for x in range(0, len(self.references), 1):
										if oref[1] in self.references[x]:
												target = self.references[x]
												try:
														for idx in oref[1:]:
															if type(target) == list and is_number(idx) == True:
																	target = target[int(idx)]
															else:
																	target = target[idx]
														obj[item] = target
												except Exception as e:
														report_error("JSON_REF_DECODER", [ "error parsing variable in settings", e, str(item), str(obj[item]) ])
														pass

				return obj

def printd(*args):
	"""
	Debug print function that only outputs messages if debug mode is enabled in the settings.

	:param args: The arguments to print.
	:return: None
	"""
	if get_setting(['debug'], False) == False:
		return
	print(' '.join(str(arg) if not isinstance(arg, list) else str(arg) for arg in args))
	print("")

def report_video_playback(source, type="video", log_only_latest=-1):
	"""
	Reports the currently playing video or commercial to the local web server for logging purposes. 
	Does nothing if debug mode is enabled.

	:param source: The path to the video or commercial file being played.
	:param type: A string indicating whether the source is a "video" or "commercial". Default is "video".
	:param log_only_latest: a number representing the amount of latest entries to retain.
	:return: None
	"""
	if get_setting(['debug'], False):
		return

	base_url = "http://127.0.0.1/"
	param = ""
	if type == "video":
		param = "current_video=" + urllib.quote_plus(ensure_string(source))
	elif type == "commercial":
		param = "current_comm=" + urllib.quote_plus(ensure_string(source))

	if param:
		if log_only_latest>0:
			param += "&log_only_latest=" + ensure_string(log_only_latest)
		contents = open_url(base_url + "?" + param)

def play_video(source, commercials, max_commercials_per_break, start_pos, bumpers=None, log_only_latest=-1):
	"""
	Play a video file using OMXPlayer on the Raspberry Pi.
	
	This is the main video playing experience.

	:param source: The path to the video file to be played.
	:param commercials: A list of commercial break times in seconds.
	:param max_commercials_per_break: The maximum number of commercials to play during a break.
	:param start_pos: The position in seconds to start the video from.
	:param bumpers: A dictionary containing 'in' and 'out' bumper video file paths (optional).
	:param log_only_latest: a number representing the amount of latest entries to retain on the web server.
	:return: None
	"""
	# launches OMXPlayer on the PI to play a video
	# If commercials are set, 2 instances of the players are loaded: one for the main video and the other for commercials (the main player is hidden during commercial breaks and then made visible again)
	# 	source: video file to be played
	# 	commercials: array of times in seconds at which the source video will be interrupt to play commercials
	#	max_commercials_per_break:
	#		If set to a number, a random commercial will be continually selected up until the supplied number has been reached
	#		if an array of commercials video file locations is provided, each commercial will be played until there are none left
	#	start_pos: attempts to resume the video from this value
	if os.path.splitext(source)[1].lower() == ".commercials":
		report_error("PLAY_LOOP", ["Error", "Commercial file supplied as video file. Something aint right...", "SOURCE", str(source)])
		return

	global last_played_video_source # the last video/commercial played, used for error reporting

	if source==None: # if no video file was supplied, we're done here
		return
	
	err_pos = 0.0
	try:

		current_position = 0 # the current position of the main video being played
		gend_commercials = None # the list of commercials to play during this video, if any
		
		if type(max_commercials_per_break) == list: #if a list of commercials was passed instead of a number, set variables
			gend_commercials = max_commercials_per_break
			if len(gend_commercials)!=0 and len(commercials)!=0:
				max_commercials_per_break = spread_division(len(gend_commercials), len(commercials))
			
			printd("MAXCOMM: " + ensure_string(max_commercials_per_break))
		else:
			if max_commercials_per_break>0:
				# if we're inserting commercials, let's make sure we can find some
				comm_source = get_random_commercial()
				if comm_source == None: 
					#couldn't find a commercial, so we won't even try to play any during the current video but we should report the error
					max_commercials_per_break = [] # setting max commercials to 0 and there are no commercials to play, overrides the passed value and disables commercials during this video
					report_error("PLAY_COMM", ["could not get a random commercial"])

		comm_player = None # the OMXPlayer instance for playing commercials
		
		print('Main video file:' + ensure_string(source))
		print("")

		report_video_playback(source, "video", ensure_string(log_only_latest))	# tell web server video we're playing
		player = OMXPlayer(source, args=get_setting(["player_settings"], ""), dbus_name="omxplayer.player" + str(random.randint(0,999))) # create the OMXPlayer instance for the main video
		sleep(0.5) # give the player a moment to load the video

		player.pause() # pause the video so we can set the position and other settings before playing
		player.play() # play the video (it was paused when loaded)
		
		player.seek(start_pos) # attempt to resume the video from the supplied position
		
		while (1):
			err_pos = 0.0
			last_played_video_source = source # set the last played video source to the main video being played
			try: # get the current position of the main video
				current_position = player.position()
			except: # if we can't get the position, the video has probably ended
				break
			
			try:
				#check to see if commercial times were passed and also check to see if the max commercials allowed is greater than 0
				if commercials and max_commercials_per_break:
					# Found a commercial break, play some commercials
					if current_position > 0 and float(current_position) >= float(commercials[0]):
						commercials.pop(0)  # Remove the triggered commercial time

						# pause the main video and hide the player from the screen
						try:
							player.hide_video()
							player.pause()
						except Exception:
							printd("player hide/pause error")

						sleep(0.5)

						commercials_per_break = [] # the list of commercials to play during this break
						if bumpers and bumpers.get("out"): # if there are bumpers defined and there are 'OUT' bumpers to play
							commercials_per_break.append(bumpers["out"].pop(0)) # add the 'OUT' bumpers to the front of the commercials list and remove it from the bumpers list

						# determine how many commercials to play during this break
						for i in range(0, max_commercials_per_break[0]):
							# add a commercial to the list of commercials to play during this break
							commercials_per_break.append(gend_commercials.pop(0) if gend_commercials else get_random_commercial())

						max_commercials_per_break.pop(0) # remove the first item from the list since we've used it
						
						if bumpers and bumpers.get("in"): # if there are bumpers defined and there are 'IN' bumpers to play
							commercials_per_break.append(bumpers["in"].pop(0)) # add the 'IN' bumpers to the end of the commercial list and remove it from the bumpers list

						comm_i = len(commercials_per_break) - 1 # set the amount of commercials to play during this break
						
						while(comm_i>=0): # loop through and play each commercial in the list
							try:
								if gend_commercials == None: # user has set a specific number of commercials per break
									comm_source = get_random_commercial() # get a random commercial
									if comm_source==None: # couldn't find a commercial, report the error and skip playing commercials
										report_error("PLAY_COMM", ["could not get a random commercial"])
										continue
								else: # user has chosen to automatically generate the amount of commercials
									if len(gend_commercials) == 0 and len(commercials_per_break) == 0: # we've run out of commercials to play, so we stop here
										break
									else: # get the next commercial from the list of commercials to play during this break
										comm_source = commercials_per_break.pop(0) # get the next commercial to play and remove it from the list
										printd("Commercials remaining:", len(commercials_per_break))

								last_played_video_source = comm_source # set the last played video source to the commercial being played
								print('Playing commercial #' + str(comm_i), comm_source) 
								print("")
								# tell web server we're playing a commercial
								report_video_playback(comm_source, "commercial")
								# load commercial in the commercial OMXplayer instance
								if comm_player == None:
									comm_player = OMXPlayer(comm_source, args=get_setting(["player_settings"], ""), dbus_name="omxplayer.player1")
									comm_player.set_video_pos(0,0,680,480)
									comm_player.set_aspect_mode('stretch')
								else: # we've already initialized the commercial player, so just load the new commercial
									comm_player.load(comm_source)
								
								sleep(0.5) # give the player a moment to load the commercial
							
								try:
									comm_player.show_video() # make sure the commercial player is visible
								except:
									printd("comm_player show error")
								
								# play commercial
								comm_player.play()

								# we need to wait until the commercial has completed
								# so we'll check for the current position of the video until it triggers an error and we can move on
								# but just in case we'll also get the length of the commercial, calculate when it should have ended, and move on if that time has been reached
								comm_length = get_length_from_file(comm_source) 	# get the length of commercial being played
								comm_start_time = time.time()						# get current time stamp
								comm_end_time = comm_start_time + comm_length + 1	# calculate at what time the commercial should have ended plus a little buffer (1 second)
								
								while (1):
									if time.time() > comm_end_time: # commercial should be over by now, so we move on
										break

									# if debug mode is enabled, we don't want to wait for the entire commercial to finish playing
									if get_setting(['debug'], False) == True and time.time() - comm_start_time > 3:
										report_debug("COMM_PLAY_LOOP", ["Commercial has been playing for more than 3 seconds, ending early"])
										comm_player.stop()
										break
										
									try: # if we can't get the current position of the commercial, we should break out of the loop and move on
										comm_position = math.floor(comm_player.position())
									except:
										break
									
									# sometimes the main player doesn't hide/pause. we should make sure that it does
									try:
										if player.is_playing() == True:
											player.hide_video()
											player.pause()
									except:
											printd("player hide/pause error")
							except Exception as exce:
								# if there was an error playing commercials, report it and resume playing main video
								report_error("COMM_PLAY_LOOP", ["error", ensure_string(exce), "SOURCE", comm_source, traceback.format_exc()])
							
							# decrement the amount of remaining commercials
							comm_i = comm_i - 1
							sleep(0.5) # give the player a moment to settle down before loading the next commercial
							
						# commercial break is over, resume main video
						player.show_video() # make sure the main video player is visible again
						player.play() # resume playing the main video
						
			except Exception as ecce:
				# if there was an error playing commercials, report it and resume playing main video
				report_error("COMM_PLAY", ["Error", ensure_string(ecce), "SOURCE", comm_source, traceback.format_exc()])
				player.show_video()
				player.play()


		err_pos = 7.0
		#main video has ended
		player.hide_video()
		sleep(0.5)
	except Exception as e:
		if(err_pos!=7.0):
			report_error("PLAY_LOOP", ["Error", ensure_string(e), "SOURCE", ensure_string(source), traceback.format_exc()])
			
		
		#kill all omxplayer instances since there was problem with the main video.
		kill_omxplayer()
		try:
			if comm_player != None:
				comm_player.quit()
		except Exception as ex:
			printd("error comm quit " + ensure_string(ex))
		try:
			if player != None:
				player.quit()
		except Exception as exx:
			printd("error player quit " + ensure_string(exx))
			
		return (err_pos, source, current_position)

	try:
		if comm_player != None:
			comm_player.quit()
	except Exception as ex:
		report_error("COMM_quit", ["Position", ensure_string(err_pos), "Error", ensure_string(ex)])
	try:
		if player != None:
			player.quit()
	except Exception as exx:
		report_error("PLAY_quit",["Position", ensure_string(err_pos), "Error", ensure_string(exx)])
	
	return

def ensure_string(value):
	"""
	Converts a value to a string if it is not already a string.

	:param value: The value to convert.
	:return: The value as a string.
	"""
	if isinstance(value, unicode):
		return value.encode("utf-8")
	elif isinstance(value, str):
		return value
	else:
		try:
			return str(value)
		except Exception as e:
			report_error("ENSURE_STRING", ["Error converting value to string", str(e)])
			return ""

def convert_percentages(expr):
    """
    Converts percentage values in a mathematical expression to their decimal equivalents.
    
    :param expr: A string containing a mathematical expression with percentage values.
    :return: A string with percentage values converted to decimal.
    """

    def repl(match):
        num = float(match.group(1))
        return str(num / 100.0)

    # Match any number (int or float) followed by %
    return re.sub(r'(\d+(?:\.\d+)?)%', repl, expr)


def eval_equation(equation, now):
	"""
	Tries to evaluate a mathematical equation string and return the result.

	:param chance: A string representing a mathematical equation.
	:param now: A datetime object representing the current date and time.
	:return: The result of the evaluated equation or 0 if an error occurs.
	"""
	# tries to take a string that should be a mathematical equation and calculate and return an answer
	# uses EVAL() but santizes by removing anything not a number, math symbol, period, or parentheses
	# replaces certain KEYWORDS to the corresponding value
	try:

		if len(equation) > 200:
			return -2 # if the equation is too long, return -2

		equation = convert_percentages(equation)  # Convert percentage values to decimal equivalents

		safe_globals = {
    		"__builtins__": {},
            "sin": math.sin,
            "cos": math.cos,
            "abs": abs,
            "min": min,
            "max": max,
            "round": round,
            "floor": math.floor,
            "ceil": math.ceil,
            "log": math.log,
            "exp": math.exp,
            "pi": math.pi,
            "e": math.e,
            "scale": lambda x: float(x) / 100.0,
            "clamp": lambda x,y=1.0: max(0.0, min(y, float(x))),
    		"day": now.day,
            "maxdays": calendar.monthrange(now.year, now.month)[1],
			"weekday": now.weekday(),
			"month": now.month,
			"hour": now.hour,
			"minute": now.minute,
			"second": now.second,
			"year": now.year
		}

		return eval(equation, safe_globals, {})
	except:
		return -1  # if there was an error, return -1

def get_setting(find, default=None):
	"""
	Searches the settings global variable for the specified setting and returns it.

	:param find: A dictionary to search for in the settings dictionary.
	:param default: The default value to return if the setting is not found. Set to None by default.
	:return: The value of the setting if found, otherwise the default value.

    Example usage:

    >>> settings = {
    ...     "version": "0.992",
    ...     "drive": ["/media/pi/ssd", "/media/pi/ssd_b"],
    ...     "channels": {
    ...         "error": "Error",
    ...         "nested": {
    ...             "deep": {
    ...                 "value": 123
    ...             }
    ...         }
    ...     }
    ... }
    >>> get_setting(['version'])
    '0.992'
    >>> get_setting(['drive', 0])
    '/media/pi/ssd'
    >>> get_setting(['channels', 'error'])
    'Error'
    >>> get_setting(['channels', 'nested', 'deep', 'value'])
    123
    >>> get_setting(['channels', 'missing'], 'not found')
    'not found'
    >>> get_setting(['drive', 10], 'fallback')
    'fallback'
	"""

	global settings
	try:
		temp = settings
		for k in find:
			if k in temp:
				temp = temp[k]
			else:
				return default
		return temp
	except Exception as e:
		report_error("GET_SETTING", ["Error accessing settings", str(e)])
		return default

def get_short_month_name(month_number):
	"""
	Returns the abbreviated name of the month for a given month number (1-12).
	
	:param month_number: An integer representing the month (1 for Jan, 2 for Feb, etc.).
	:return: The abbreviated name of the month (e.g., "Jan", "Feb", etc.).
	"""
	month_abbr = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"]
	if 1 <= month_number <= 12:
		return month_abbr[month_number - 1]
	else:
		raise ValueError("Month number must be in the range 1-12")

def readkey():
	"""
	Reads a single key press from the terminal without waiting for Enter to be pressed.
	:return: The character of the key pressed.
	"""
	import sys, tty, termios
	fd = sys.stdin.fileno()
	old = termios.tcgetattr(fd)
	try:
		tty.setraw(fd)
		return sys.stdin.read(1)
	finally:
		termios.tcsetattr(fd, termios.TCSADRAIN, old)

def replace_special_words(date_str):
	"""
	Replaces special words in the date string with their corresponding values.

	:param date_str: The date string containing placeholders.
	:return: The date string with placeholders replaced by actual values.
	"""
	global now
	# Create a dictionary for placeholders and their replacement values
	replacements = {
		"%HOUR%": now.strftime("%I"),
		"%AMPM%": now.strftime("%p"),
		"%MIN%": now.strftime("%M"),
		"%MONTH%": get_short_month_name(now.month),
		"%DAY%": "{:02d}".format(now.day),
		"%YEAR%": str(now.year)
	}

	# Replace each placeholder with its corresponding value
	for key, value in replacements.items():
		if key in date_str:
			date_str = date_str.replace(key, value)

	# Handle %MAXDAYS% separately because it requires additional logic to determine the total days in the current month which may vary by month and year
	if "%MAXDAYS%" in date_str:
		# Extract the month
		month_str = date_str.split(" ")[0]
		
		# Determine the year (current year)
		current_year = now.year
		
		# Find the last day of the month
		month_num = datetime.datetime.strptime(month_str, "%b").month
		next_month = datetime.datetime(current_year, month_num % 12 + 1, 1)
		max_days = (next_month - datetime.timedelta(days=1)).day
		
		# Replace %MAXDAYS% with the calculated max days
		date_str = date_str.replace("%MAXDAYS%", str(max_days))
	return date_str

def is_within_range(data):
	"""
	Checks if the current date and time fall within the specified ranges.

	:param data: A dictionary containing date, time, and year ranges.
	:return: True if the current date and time fall within the ranges, False otherwise.
	"""
	global now # always use the global 'now' variable for date and time
	current_datetime = now
	current_date = current_datetime.strftime("%b %d")
	current_time = current_datetime.strftime("%I:%M%p")
	current_year = now.year

	date_ranges = data.get("dates", [])
	time_ranges = data.get("times", [])
	year_ranges = data.get("years", [])

	# Check if the current year is within any of the year ranges
	year_within_range = True
	for year_range in year_ranges:
		year_within_range = False
		if replace_special_words(str(year_range)) == str(current_year):
			year_within_range = True
			break

	# Check if the current date is within any of the date ranges
	date_within_range = True
	for date_range in date_ranges:
		date_within_range = False
		if isinstance(date_range, list): # a list of dates
			start_date_str = replace_special_words(date_range[0])
			end_date_str = replace_special_words(date_range[1])
		
			start_date = datetime.datetime.strptime(start_date_str + " " + str(now.year), "%b %d %Y")
			end_date = datetime.datetime.strptime(end_date_str + " " + str(now.year), "%b %d %Y")
			if start_date.date() <= current_datetime.date() <= end_date.date():
				date_within_range = True
				break
		else: # a single date
			start_date_str = replace_special_words(date_range)
			start_date = datetime.datetime.strptime(start_date_str + " " + str(now.year), "%b %d %Y")
			if start_date.date() == current_datetime.date():
				date_within_range = True
				break
	
	# Check if the current time is within any of the time ranges
	time_within_range = True
	for time_range in time_ranges:
		time_within_range = False
		if isinstance(time_range, list): # a list of dates
			start_time_str = replace_special_words(time_range[0])
			end_time_str = replace_special_words(time_range[1])

			check_start_time = datetime.datetime.strptime(start_time_str, "%I:%M%p")
			end_time = datetime.datetime.strptime(end_time_str, "%I:%M%p")
			if check_start_time.time() <= current_datetime.time() <= end_time.time():
				time_within_range = True
				break
		else:
			start_time_str = replace_special_words(time_range)
			check_start_time = datetime.datetime.strptime(start_time_str, "%I:%M%p")
			if check_start_time.time() == current_datetime.time():
				time_within_range = True
				break
	
	return date_within_range and time_within_range and year_within_range

def calculate_fill_time(video_length, current_time, offset=0):
	"""
	Calculates the number of seconds needed to align a video's end time
	to the next half-hour or top-of-the-hour mark, rolling forward if
	the remaining time is less than 60 seconds.

	:param video_length: Length of the video in seconds.
	:param current_time: Current time as a datetime object.
	:return: Time in seconds required to reach the next interval.
	"""
	# Extract current hour, minute, and second
	curr_hour = current_time.hour
	curr_minute = current_time.minute
	curr_second = current_time.second
	total_seconds_past_hour = (curr_minute * 60) + curr_second  # Seconds past the last full hour

	# Determine next alignment point
	next_target_seconds = 1800 if total_seconds_past_hour < 1800 else 3600

	# Roll forward if necessary, ensuring we always land on a half-hour/full-hour boundary
	while total_seconds_past_hour + video_length >= next_target_seconds:
		next_target_seconds += 1800  # Move forward in 30-minute increments

	# Calculate the remaining time to fill
	fill_time = next_target_seconds - (total_seconds_past_hour + video_length)

	# If the remaining time is less than 60 seconds, roll forward again
	if fill_time < 60:
		next_target_seconds += 1800  # Move to the next half-hour/full-hour mark
		fill_time = next_target_seconds - (total_seconds_past_hour + video_length)

	return max(fill_time + offset, 0)  # Avoid negative values

def check_video_times(obj, channel=None, allow_chance=True): # check to see if the current time falls within the programming schedule
	"""
	Checks the current time against a list of programming times and returns the first matching schedule.

	:param obj: A list of dictionaries containing programming times and conditions.
	:param channel: The channel to check against (optional).
	:param allow_chance: A boolean indicating whether to allow chance-based programming (default is True).
	:return: A list containing the programming schedule if a match is found, otherwise None.
	"""
	try:
		update_current_time() # make sure we have the latest time
		global now # always use the global 'now' variable for date and time
		global current_video_tag # current tag, if set

		month = now.month
		now_h = now.hour
		now_m = now.minute
		now_d = now.weekday()
		ddm = now.day
		now_y = now.year
		
		dayOfWeek = getDayOfWeek(now_d)
		

		skip = dict (
			month = None,
			date = None,
			dayOfWeek = None,
			time = None,
			special = None,
			chance = None,
			between = None,
			channel = None
		)

		for timeItem in reversed(obj):
			is_static = [False, 0, ""]
			skip = dict (
				month = False,
				date = False,
				dayOfWeek = False,
				time = False,
				special = False,
				chance = False,
				between = False,
				channel = False
			)
			
			video_type = "video"

			if 'type' in timeItem: # check if it's a show or commercial
				if timeItem['type'] != None:
					video_type = timeItem['type']

			if 'channel' in timeItem: # check if we should be using special channels
				if timeItem['channel'] == None and channel == None:
					skip['channel'] = False
				elif timeItem['channel'] in wildcard_array():
					skip['channel'] = False
				else:
					if timeItem['channel'] != channel:
						continue

			if 'special' in timeItem:
				if timeItem['special'] != None:
					if is_special_time(timeItem['special']) == False:
						continue

			if 'chance' in timeItem:
				chance_eval = timeItem['chance'] # store chance string
				if type(chance_eval) != float:
					chance_eval = eval_equation(chance_eval, now)
				if random.random() > float(chance_eval) or allow_chance == False:
					continue

			if 'between' in timeItem:
				if timeItem['between'] != None:
					if is_dict(timeItem['between']):
						if is_within_range(timeItem['between'])==False:
							continue
					else:
						report_error("CHECK_TIMES", ["between is not a dictionary and it should be", str(timeItem['between'])])
						
			if 'month' in timeItem:
				if timeItem['month'] != None:
					test = False
					for itemX in timeItem['month']:
						if itemX == month or itemX in wildcard_array(): # if it's the proper month, we proceed
							test = True
							break
					if test==False:
						continue

			if 'date' in timeItem:
				if timeItem['date'] != None:
					test = False
					for itemX in timeItem['date']:
						if itemX == ddm or itemX in wildcard_array(): # if it's on the proper day, we proceed
							test = True
							break
					if test==False:
						continue

			if 'dayOfWeek' in timeItem:
				if timeItem['dayOfWeek'] != None:
					test = False
					for itemX in timeItem['dayOfWeek']:
						if itemX.lower() == dayOfWeek or itemX in wildcard_array(): # if it's the proper day of the week, we proceed
							test = True
							break
					if test==False:
						continue

			if 'tag' in timeItem: #  if current video is tagged, we need to check if any programming matches it
				if current_video_tag == None:
					continue
				if timeItem['tag'].lower() != current_video_tag.lower():
					continue

			if 'start' in timeItem and 'end' in timeItem:
				ntime = now
				if timeItem['start'][0] in wildcard_array():
					timeItem['start'][0] = now_h
				if timeItem['start'][1] in wildcard_array():
					timeItem['start'][1] = now_m
				if timeItem['end'][0] in wildcard_array():
					timeItem['end'][0] = now_h
				if timeItem['end'][1] in wildcard_array():
					timeItem['end'][1] = now_m
			
				stime = datetime.datetime.strptime(str(now.day).zfill(2) + "/" + str(now.month).zfill(2) + "/" + str(now.year).zfill(4) + " " + str(timeItem['start'][0]) + ":" + str(timeItem['start'][1]) + ":00", "%d/%m/%Y %H:%M:%S")
				etime = datetime.datetime.strptime(str(now.day).zfill(2) + "/" + str(now.month).zfill(2) + "/" + str(now.year).zfill(4) + " " + str(timeItem['end'][0]) + ":" + str(timeItem['end'][1]) + ":59", "%d/%m/%Y %H:%M:%S")
				printd(timeItem['name'], str(stime), now, ntime, etime)
				if ntime >= stime and ntime <= etime:
					skip['time'] = False
				else:
					continue

			if 'static' in timeItem: # this flag establishes that this schedule should stay triggered until the set time runs out
				if timeItem['static'] != None:
					if is_number(timeItem['static'][1]) == True:
						is_static = timeItem['static']
		
		
			#useThisOne = True
			#for itemX in skip:
			#	if skip[itemX] == True:
			#		useThisOne = False
			#		break
			
			#returns: name, true if chance triggers, type of video, whether static is set, the entire programming block, and the current time
			return [timeItem['name'], True if skip['chance']==False else False, video_type, is_static, timeItem, now]
	except Exception as valerr:
		report_error("CHECK_TIMES", [str(valerr),traceback.format_exc()])

	return None

def get_video_tag(video_file):
	"""
	Returns the video tag from the video file name.

	:param video_file: The video file path.
	:return: The video tag or None if not found.
	"""
	# The tag is expected to be in the format @tag@ in the file name
	matches = re.findall(r'@([^@]+)@', video_file)
	if matches:
		return matches[-1].split()[-1]
	return None

def generate_commercials_list(total_time_seconds, multiplier=50):
	"""
	Fills the given total time with randomly selected choices from a random commercial. 

	:param total_time_seconds: Total time in seconds.
	:param multiplier: A multiplier to adjust the maximum time allowed to generate the list of commercials. Default is 50.
	:return: A list of randomly selected commercials that (roughly) sum up to the total time.
	"""
	max_time_before_timeout = max(((float(total_time_seconds)/100) / 100) * multiplier, .1)
	printd("Max time:", max_time_before_timeout)
	remaining_time = total_time_seconds
	selected_intervals = []
	gen_start_time = time.time()

	while True:
		c = get_random_commercial()
		if c == None:
			report_error("GEN_COMM_LIST", ["could not get a random commercial"])
			continue
		
		cTime = get_length_from_file(c)
		if cTime==None:
			report_error("GEN_COMM_LIST", ["commercial file has no length", str(c)])		
		
		while(cTime == None):
			c = get_random_commercial()
			if c == None:
				report_error("GEN_COMM_LOST", ["could not get a random commercial"])
				continue
			cTime = get_length_from_file(c)
			if cTime==None:
				report_error("GEN_COMM_LIST", ["commercial file has no length", str(c)])		

	
		if remaining_time - cTime > 1:
			selected_intervals.append(c)
			remaining_time -= cTime
		
		if time.time() - gen_start_time > max_time_before_timeout:
			printd("taking too long to generate commercials, stopping", remaining_time)
			break
		if remaining_time < 20:
			printd("Remaining time:", remaining_time)
			break
	random.shuffle(selected_intervals) # shuffle the list of commercials to randomize the order
	return selected_intervals

def get_args(index):
	"""
	Returns the command line argument at the specified index.

	:param index: The index of the command line argument to retrieve.
	:return: The command line argument at the specified index or None if not found.
	"""
	if index < len(sys.argv):
		return sys.argv[index]
	return None 

def get_commercials(source):
	"""
	Returns a list of times as defined in the commercial file from the specified source video file.

	:param source: The source video file path.
	:return: A list of commercials in seconds or an empty list if not found.
	"""
	#commercials file name must match exactly the source video with the file extension '.commercials'
	#each line of a .commercials file will be the time of a commercial break in seconds (and milliseconds if required) ex: 620.34
	#first line can be text and number to set a minimum length time for the video plus inserted commercials ex: length:MIN_LENGTH

	#load single file for all commercials
	#usefull when commercial breaks aren't easily identifiable for a lot of episodes from a single show
	#first 8 chrs need to exactly the same
	if os.path.isfile(source[:8] + '.commercials.master') == True:
		with open(source[:8] + '.commercials.master') as temp_file:
			return [float(line.rstrip()) for line in temp_file]
	
	#load individual file for video
	if os.path.isfile(source + '.commercials') == True:
		ret = []
		with open(source + '.commercials') as temp_file:
			for i,line in enumerate(temp_file):
				if(line.rstrip().isdigit()):
					ret.append(float(line.rstrip()))
				else:
					ret.append(str(line.rstrip()))
		return ret
	return []

def get_length_from_file(file):
	"""
	Returns the length of the video file in seconds.
	
	:param file: The video file path.
	:return: The length of the video in seconds or None if not found.
	"""
	# instead of using ffmpeg or whatever to get the video's length
	# it is stored in the file name
	# %T(TOTAL_LENGTH_IN_SECONDS)%
	try:
		start = file.index("%T(") + 3
		end = file.index(")%", start)
		if is_number(file[start:end]):
			return float(file[start:end])
		else:
			return None
	except Exception as ValueError:
		# if the file name doesn't contain the length, report the error and return None
		report_error("GET_VIDEO_LENGTH", [str(ValueError), str(file)])
		return None

def get_random_video_from_dir(dir):
	"""
	Returns a random video from the specified directory that falls within the specified length range.

	:param dir: The directory to search for videos.
	:param min_len: The minimum length of the video in seconds. Default is 0.
	:param max_len: The maximum length of the video in seconds. Default is 99999.
	:return: A random video file path or None if no videos are found.
	"""
	try:

		videos = get_videos_from_dir_cached(dir)
		if len(videos) == 0:
			return None
		random.shuffle(videos) # shuffle the list of videos to randomize the order
		return random.choice(videos)
	except Exception as valerr:
		#an unknown error occurred, report it and return nothing		
		report_error("GET_RND_VIDEO", ["##unexpected error##", str(valerr), traceback.format_exc()])
		return None

def get_random_commercial():
	"""
	Returns a random commercial from the list of commercials in the settings file.
	
	:return: A random commercial file path or None if no commercials are found.
	"""
	global settings

	try:
		programming_schedule = check_video_times(settings['commercial_times'], get_current_channel()) # check to see if any commercials are scheduled
		if programming_schedule == None:
			#no specified commercials were set for this time perioid, even tho the settings suggests there might be
			report_error("GET_RND_COMM", ["No commercials block found", "check settings.json file"]) #could be intentional, but we're going to report it just in case
			return None

		min_len=0
		if 'min-length' in programming_schedule[4]:
			min_len = programming_schedule[4]['min-length']
			if not is_number(min_len): # check to see if the min-length is a number
				report_error("COMMERCIALS", ["min-length is set but it is not a number", "check settings.json file", programming_schedule[4]])

		max_len=99999 # 2 days worth of seconds
		if 'max-length' in programming_schedule[4]:
			max_len = programming_schedule[4]['max-length']
			if not is_number(max_len): # check to see if the max-length is a number
				report_error("COMMERCIALS", ["max-length is set but it is not a number", "check settings.json file", programming_schedule[4]])

		folder = programming_schedule[0] # the results, if any, returns None type of not
		if folder:
			#commercials are scheduled, so let's gather the videos from the specified paths (could only be 1 or could be many)

			# going to loop through all returned directories, replacing special keywords and validating that the directory does exist
			new_folder=[] # temp list of new directories
			for i in range(0, len(folder)):
				tmp_folder = replace_all_special_words(folder[i]) # replace special keywords
				if os.path.isdir(tmp_folder) == False: # folder doesn't exist
					report_error("GET_RND_COMM", [folder, "directory does not exist", "check settings.json file"]) # report error that directory exist
				else:
					new_folder.append(tmp_folder) # add directories that do exist
			
			if len(new_folder)==0: # no directories exist
				report_error("GET_RND_COMM", ["No searachble directories are found", "check settings.json file"])
				return None
			
			folder = new_folder # replace old folders with the newly verified folders
			# settings wants the random video to be selected from multiple folders but prefers the random video come from the folders supplied rather than the combine contents of each folder
			# this can help balance the types of videos played when supplying different folders where one type with more videos would be more randomly favored
			folder_chance = None 
			if 'prefer-folder' in programming_schedule[4]:
				folder_chance = programming_schedule[4]['prefer-folder']
			
			if folder_chance != None: # check to see if the user has set a folder preference
				if 'weighted' in programming_schedule[4]: # check to see if the user has set a weighted folder selection
					if len(programming_schedule[4]['weighted']) != len(folder): # check to see if the weighted folder count matches the folder count
						report_error("GET_RND_COMM", ["Weighted folder count supplied do not match folder count", "check settings.json file", programming_schedule[4]]) # report error if they don't match
						random.shuffle(folder) # shuffle the list of folders to randomize the order
						rfolder = random.choice(folder) # also if they don't match, just select a random folder
					else:
						rfolder = weighted_random_choice(folder, programming_schedule[4]['weighted']) # select a folder based on the weighted values
						if rfolder == None:
							report_error("GET_RND_COMM", ["Weighted folder selection failed", "check settings.json file", programming_schedule[4]]) # report error if the weighted values are invalid
							random.shuffle(folder) # shuffle the list of folders to randomize the order
							rfolder = random.choice(folder) # if the weighted values are invalid, just select a random folder
				else:
					random.shuffle(folder) # shuffle the list of folders to randomize the order
					rfolder = random.choice(folder) # if no weighted values are set, just select a random folder
				
				video = get_videos_from_dir_cached(replace_all_special_words(rfolder), min_len, max_len) # get the videos from the selected folder
			else: # no folder preference set
				video = get_videos_from_dir_cached(replace_all_special_words(folder[0]), min_len, max_len) # get the videos from the first folder in the list
				for itemX in range(1,len(folder)): # loop through the rest of the folders
					video = video + get_videos_from_dir_cached(replace_all_special_words(folder[itemX]), min_len, max_len) # add the videos from the folder to the list of videos
			
			random.shuffle(video) # shuffle the list of videos to randomize the order
			return random.choice(video) #return a random video from the combined paths
	except Exception as valerr:
		#an unknown error occurred, report it and return nothing		
		report_error("GET_RND_COMM", ["##unexpected error##", "check settings.json file", str(valerr), traceback.format_exc()])
		return None	

channel_name_static = None
last_channel_name = None
def get_current_channel():
	"""	Returns the current channel name based on settings or external file. """
	global channel_name_static
	# channel names can be set in the settings file to correspond with programming
	#
	# check to see if the script has set a channel.
	if channel_name_static != None:
		return channel_name_static
	
	# check for external channel
	# a plain text file containing a 'channel' name 
	global settings
	if not get_setting(['channels', 'file']):
		return None
	if not os.path.exists(settings['channels']['file']):
		return None
	if os.path.exists(settings['channels']['file']) == False:
		return None
	
	global last_channel_name
	file = open(settings['channels']['file'])
	line = file.read()
	file.close()
	if line != last_channel_name:
		nc = line
		if nc == "":
			nc = "default"
		report_error("CHANNEL", ["Channel set to: " + nc])
		last_channel_name = line
	if line == "":
		return None
	return line

def getDayOfWeek(d):
	"""
	Returns the name of the day of the week for a given integer.

	:param d: An integer representing the day of the week (0=Monday, 6=Sunday).
	:return: The name of the day of the week in lowercase.
	"""
	return calendar.day_name[d].lower()
		
def getMonth(m):
	"""
	Returns the month name for a given month number.

	:param m: The month number (1-12).
	:return: The name of the month.
	"""
	return [None,'january','february','march','april','may','june','july','august','september','october','november','december'][m % 12 or 12]

def get_folders_from_server(showType, showDir):
	"""
	Returns a list of available shows from the server.

	:param showType: The type of show to search for.
	:param showDir: The directory to search in.
	:return: A list of available shows.
	"""
	contents = open_url("http://127.0.0.1/?getavailable=" + urllib.quote_plus(ensure_string(showType)) + "&dir=" + urllib.quote_plus(ensure_string(showDir)))
	if contents == '0':
		return None
	return contents.split("\n")

def get_folders_from_dir(path):
	"""
	Returns a list of directories in the specified path.

	:param path: The path to search for directories.
	:return: A list of directories found in the specified path.
	"""
	dirs = os.walk(path).next()[1]
	for i in range(len(dirs)):
		dirs[i] = path + "/" + dirs[i]
	return dirs

def clean_up_cache_files(dir_name,dir):
	"""
	Removes cache files from the specified directory that match the given directory name.

	:param dir_name: The directory where the cache files are located.
	:param dir: The directory name to match against the cache files.
	"""
	test = os.listdir(dir_name)

	for item in test:
		if item.endswith(".cache"):
			if item.split()[0] == dir:
				os.remove(os.path.join(dir_name, item))



def is_cache_valid(cache_path, dir_path):
	"""
	Checks if the cache file is valid by comparing the cached filenames with the current filenames in the directory.

	:param cache_path: The path to the cache file.
	:param dir_path: The directory to check against the cached filenames.
	
	:return: True if the cache is valid, False otherwise.
	"""
	try:
		# Read cached filenames
		with open(cache_path, 'r') as f:
			cached_filenames = set(json.load(f))

		# Get current filenames in target directory (non-recursive)
		current_filenames = set(os.listdir(dir_path))

		# Compare sets
		if cached_filenames == current_filenames:
			return True
		else:
			return False

	except Exception as e:
		report_error("CACHE_VALIDATION", ["Error validating cache", ensure_string(e)])
		return False

def get_videos_from_dir(dir_path, min_length=GET_VIDEOS_FROM_DIR_MIN_DURATION, max_length=GET_VIDEOS_FROM_DIR_MAX_DURATION):
	"""
	Loads all video filenames from a directory into an array, using caching and OS modification time checks.
	Caches all filenames, but filters the returned results based on length.

	:param dir_path: The directory to search for video files.
	:param min_length: Minimum length (in seconds) for video files to be returned. Defaults to 0.
	:param max_length: Maximum length (in seconds) for video files to be returned. Defaults to 99999.
	:return: A list of full paths to video files found in the directory that meet the length criteria.
	"""

	cache_dir_name = get_setting(['cache_path'], os.path.join(os.path.dirname(os.path.realpath(__file__)), "cache"))
	
	sanitized_dir = re.sub(r'[^a-zA-Z0-9]', '', dir_path)
	cache_fname = os.path.join(cache_dir_name, "{}.cache".format(sanitized_dir))

	if not os.path.exists(cache_dir_name):
		os.makedirs(cache_dir_name)

	# Regex to extract the video length
	length_regex = re.compile(r'%T\((\d+)\)%')

	all_filenames = [] # This will hold all filenames, whether from cache or fresh scan

	# Check if cache exists and is up-to-date
	if os.path.exists(cache_fname):
		cache_mtime = os.path.getmtime(cache_fname) # Get the last modified time of the cache file
		dir_mtime = os.path.getmtime(dir_path) # Get the last modified time of the directory

		update_cache = False # Flag to determine if we need to update the cache

		if cache_mtime < dir_mtime: # If the cache file is older than the directory, we need to update it
			update_cache = True 
		elif not is_cache_valid(cache_fname, dir_path): # If the cache is not valid, we also need to update it
			update_cache = True

		if not update_cache: # Cache is valid and up-to-date
			with open(cache_fname, 'r') as f:
				all_filenames = json.load(f)
		else: # Cache is outdated, rescan and update cache
			filenames_from_scan = [] 
			for ext in VIDEO_EXTENSIONS: # Loop through each video extension
				for full_file_path in glob.glob(os.path.join(dir_path, '*.{}'.format(ext))): # Find all files with the current extension
					filenames_from_scan.append(os.path.basename(full_file_path)) # Append the base name of the file to the list
			
			with open(cache_fname, 'w') as f: # Write the newly scanned filenames to the cache file
				json.dump(filenames_from_scan, f) 
			all_filenames = filenames_from_scan 
	else: # Cache does not exist, rescan and create cache
		filenames_from_scan = []
		for ext in VIDEO_EXTENSIONS:
			for full_file_path in glob.glob(os.path.join(dir_path, '*.{}'.format(ext))):
				filenames_from_scan.append(os.path.basename(full_file_path))
		
		with open(cache_fname, 'w') as f: # Write the scanned filenames to the cache file
			json.dump(filenames_from_scan, f)
		all_filenames = filenames_from_scan

	# Now, filter the 'all_filenames' based on min_length and max_length for the return result
	filtered_results_full_paths = []
	for filename in all_filenames:
		match = length_regex.search(filename)
		if match:
			try:
				video_length = int(match.group(1))
				if min_length <= video_length <= max_length:
					filtered_results_full_paths.append(os.path.join(dir_path, filename))
			except ValueError:
				# Handle cases where the extracted group might not be a valid integer
				pass # Or log a warning

	return filtered_results_full_paths

_cached_get_videos_from_dir = {}

def get_videos_from_dir_cached(dir_path, min_length=GET_VIDEOS_FROM_DIR_MIN_DURATION, max_length=GET_VIDEOS_FROM_DIR_MAX_DURATION):
	"""
	Retrieves video files from a directory while avoiding hitting the file system by storing the cache in a variable for immediate access in the near future.

	:param dir_path: The directory to search for video files.
	:param min_length: Minimum length (in seconds) for video files to be returned. Defaults to 0.
	:param max_length: Maximum length (in seconds) for video files to be returned. Defaults to 99999.

	:return: A list of full paths to video files found in the directory that meet the length criteria.
	"""
	if not os.path.isdir(dir_path):
		return []

	global _cached_get_videos_from_dir
	now = time.time()

	cache_key = (dir_path, min_length, max_length)
	cache_entry = _cached_get_videos_from_dir.get(cache_key)

	# Use cached data if it exists and is recent
	if cache_entry and now - cache_entry['timestamp'] < 120:  # 2 min freshness window
		return cache_entry['results']

    # Otherwise, refresh from disk
	results = get_videos_from_dir(dir_path, min_length, max_length)
	_cached_get_videos_from_dir[cache_key] = {
		'timestamp': now,
		'results': results
	}
	return results


def get_uptime(start_time):
	"""
	Returns the uptime of the script in seconds.

	:param start_time: The time the script started.
	:return: The uptime in seconds.
	"""
	return (time.time() - start_time)

def is_date_within_range(date1, date2, range_days):
	"""	
	Checks if the difference between two dates is within a specified range of days.	

	:param date1: The first date to compare.
	:param date2: The second date to compare.
	:param range_days: The range of days to check against.
	:return: True if the difference is within the range, False otherwise.
	"""
	delta = (date1 - date2).days
	if (range_days >= 0 and 0 <= delta <= range_days) or (range_days < 0 and range_days <= delta <= 0):
		return True
	return False


def is_dict(var):
	"""
	Checks if the variable is a dictionary.

	:param var: The variable to check.
	:return: True if the variable is a dictionary, False otherwise.
	"""
	return isinstance(var, dict)

def IsEaster(daysFromEaster):
	"""
	Checks if the current date is Easter or within a specified range of days from Easter.

	:param daysFromEaster: Number of days from Easter to check.
	:return: True if within range, False otherwise.
	"""
	global now	
	# Using the Anonymous Gregorian algorithm to calculate Easter Sunday
	year = now.year  # Determine the year from the global variable 'now'
	a = year % 19
	b = year // 100
	c = year % 100
	d = b // 4
	e = b % 4
	f = (b + 8) // 25
	g = (b - f + 1) // 3
	h = (19 * a + b - d - g + 15) % 30
	i = c // 4
	k = c % 4
	l = (32 + 2 * e + 2 * i - h - k) % 7
	m = (a + 11 * h + 22 * l) // 451
	month = (h + l - 7 * m + 114) // 31
	day = ((h + l - 7 * m + 114) % 31) + 1
	
	easter_sunday = datetime.date(year, month, day)
	#target_date = easter_sunday + datetime.timedelta(days=daysFromEaster)
	return is_date_within_range(datetime.date(now.year, now.month, now.day), easter_sunday, daysFromEaster)

from datetime import date, timedelta

def IsFathersDay(daysFromFathersDay=0):
	"""
	Checks if the current date is Father's Day or within a specified range of days from Father's Day.

	:param daysFromFathersDay: Number of days from Father's Day to check.
	:return: True if within range, False otherwise.
	"""

	global now	
	june_first = datetime.date(now.year, 6, 1) # Get June 1st of the current year
	first_sunday = june_first + datetime.timedelta(days=(6 - june_first.weekday() + 7) % 7) # Find the first Sunday of June
	fathers_day_date = first_sunday + datetime.timedelta(weeks=2) # Calculate Father's Day (third Sunday of June)

	return is_date_within_range(datetime.date(now.year, now.month, now.day), fathers_day_date, daysFromFathersDay)

import datetime

def IsMemorialDay(daysFromMemorialDay=0):
    """
    Checks if the current date is Memorial Day or within a specified range of days from Memorial Day.

    :param daysFromMemorialDay: Number of days from Memorial Day to check.
    :return: True if within range, False otherwise.
    """

    global now  
    may_last = datetime.date(now.year, 5, 31)  # Get May 31st of the current year
    last_monday = may_last - datetime.timedelta(days=may_last.weekday())  # Find the last Monday of May

    return is_date_within_range(datetime.date(now.year, now.month, now.day), last_monday, daysFromMemorialDay)

def IsMothersDay(daysFromMothersDay=0):
	"""
	Checks if the current date is Mother's Day or within a specified range of days from Mother's Day.

	:param daysFromMothersDay: Number of days from Mother's Day to check.
	:return: True if within range, False otherwise.
	"""

	global now
	may_first = date(now.year, 5, 1)  # May 1st of the current year
	first_sunday = may_first + timedelta(days=(6 - may_first.weekday() + 7) % 7)  # First Sunday of May
	mothers_day_date = first_sunday + timedelta(weeks=1)  # Second Sunday of May

	return is_date_within_range(datetime.date(now.year, now.month, now.day), mothers_day_date, daysFromMothersDay)

def is_number(s):
	""" 
	Checks if the string is a number by testing if it can be converted to a float.

	:param s: The string to check.
	:return: True if it can be converted, False otherwise.
	"""

	try:
		float(s)
		return True
	except ValueError:
		return False

def is_special_time(check):
	"""
	Checks if the current date is a special holiday or event.

	:param check: The string to check for special dates.
	:return: True if the date is special, False otherwise.
	"""
	if check[:4].lower() == 'xmas':
		if is_number(check[4:].strip()):
			num = int(check[4:])
		else:
			return True if IsThanksgiving(8) and IsXmas(-25) else False
		return True if IsXmas(num) else False

	if check[:12].lower() == 'thanksgiving':
			num = 0
			if is_number(check[12:].strip()):
					num = int(check[12:])
			return True if IsThanksgiving(num) else False

	if check[:6].lower() == 'easter':
		num = 0
		if is_number(check[6:].strip()):
			num = int(check[6:])
		return True if IsEaster(num) else False

	if check[:11].lower() == 'mothers day':
		num = 0
		if is_number(check[11:].strip()):
			num = int(check[11:])
		return True if IsMothersDay(num) else False

	if check[:11].lower() == 'fathers day':
		num = 0
		if is_number(check[11:].strip()):
			num = int(check[11:])
		return True if IsFathersDay(num) else False

	if check[:12].lower() == 'memorial day':
		num = 0
		if is_number(check[12:].strip()):
			num = int(check[12:])
		return True if IsMemorialDay(num) else False

	return False

def kill_omxplayer():
	command = "/usr/bin/sudo pkill omxplayer"
	process = subprocess.Popen(command.split(), stdout=subprocess.PIPE)
	output = process.communicate()[0]
	print(output)

def now_totheminute():
	"""Returns the current datetime rounded to the minute."""
	now = datetime.datetime.now()
	return now.replace(second=0, microsecond=0)

def open_url(url):
	"""
	Opens a URL and returns the response content.

	:param url: The URL to open.
	:return: The content of the response.
	"""
	try:
		
		if get_setting(['debug'], False) == True:
			printd(url)
		return urllib2.urlopen(url).read()
	except:
		return None

def IsXmas(daysFromXmas):
	"""
	Checks if the current date is Christmas or within a specified range of days from Christmas.

	:param daysFromXmas: Number of days from Christmas to check.
	:return: True if within range, False otherwise.
	"""
	global now # always use the global datetime 'now' so it doesn't break test dates
	
	xmas = datetime.datetime.strptime(str("Dec 25 " + str(now.year) + " " + str(now.hour).zfill(2) + ":" + str(now.minute).zfill(2)), '%b %d %Y %H:%M')
	target_date = xmas + datetime.timedelta(days=daysFromXmas)
	return is_date_within_range(now, xmas, daysFromXmas)

def IsThanksgiving(daysFromThanksgiving):
	"""
	Checks if the current date is Thanksgiving or within a specified range of days from Thanksgiving.

	:param daysFromThanksgiving: Number of days from Thanksgiving to check.
	:return: True if within range, False otherwise.
	"""
	global now # always use the global datetime 'now' so it doesn't break test dates
	
	year = str(now.year)
	d = datetime.datetime.strptime(str("Nov 1 " + year), '%b %d %Y')
	dw = d.weekday()
	thanksgiving = datetime.datetime.strptime(str("Nov " + str(22 + (10 - dw) % 7) + " " + str(d.year) + " " + str(now.hour).zfill(2) + ":" + str(now.minute).zfill(2)), '%b %d %Y %H:%M')
	target_date = thanksgiving + datetime.timedelta(days=daysFromThanksgiving)
	return is_date_within_range(now, thanksgiving, daysFromThanksgiving)

def PastThanksgiving(is_thanksgiving):
	"""
	Checks if the current date is Thanksgiving or past Thanksgiving and on or before Christmas.

	:param is_thanksgiving: If True, check if today is Thanksgiving.
	:return: True if past Thanksgiving, False otherwise.
	"""
	global now # always use the global datetime 'now' so it doesn't break test dates
	
	year = str(now.year)
	d = datetime.datetime.strptime(str("Nov 1 " + year), '%b %d %Y')
	dw = d.weekday()
	datme = datetime.datetime.strptime(str("Nov " + str(22 + (10 - dw) % 7) + " " + str(d.year) + " " + str(now.hour).zfill(2) + ":" + str(now.minute).zfill(2)), '%b %d %Y %H:%M')

	if is_thanksgiving: #check if today is thanksgiving
		if now==datme and now.month==now.month:
			return True
	else:
		if now > datme:
			if now.month>=12 and now.day>25:
				#it's past thanksgiving and also past xmas
				return False
			else:
				#past thanksgiving and is xmas or before
				return True
	return False

def replace_all_special_words(s):
	"""
	Replaces special keywords in a string with their actual values.
	Handles keywords like %D[index]%, %day%, %day_of_week%, %month%, etc.

	:param s: The string to process.
	:return: The processed string with special keywords replaced.
	"""
	global settings
	global now
	d = now.weekday()
	month = now.month
	# drive(s) location is set via an array in the settings.
	# iterate each drive replacing the special keyword with the actual drive location
	# 		%D[index]%
	for i in range(len(settings['drive'])):
		s = s.replace("%D[" + str((i+1)) + "]%", settings['drive'][i])
	# the day of the week and month special keywords can be replaced with the current day or week or month
	for r in [["%day%", str(d)], ["%day_of_week%", getDayOfWeek(d)], ["%month%", getMonth(month)], ["%prev-month%", getMonth(month-1)], ["%next-month%", getMonth(month+1)]]:
		s = s.replace(*r)
	return s

last_report_error = [0, 0, 0]
def report_debug(type, input, local_only=False):
	"""	
	Reports debug information to the local server and prints it to the console.
	
	:param type: The type of debug information.
	:param input: The debug information to report.
	:param local_only: If True, only report to the local server.
	"""
	global settings
	if 'debug' in settings:
		if settings['debug'] == True:
			report_error("DEBUG[" + type + "]", input + ["Debug mode is Enabled, disable it in settings"], False) # debug reports are always reported

def report_error(type, input, local_only=False):
	"""
	Reports an error to the local server and prints it to the console.
	Also checks if the error is in a loop and exits if it is.

	:param type: The type of error.
	:param input: The error message or data to report.
	:param local_only: If True, only report to the local server.
	"""

	try:
		global settings
		global last_played_video_source
		global last_report_error
		message = ""
		
		# last reported error was less than 3 seconds ago
		if (time.time() - last_report_error[0]) < 3:
			# over 100 errors reported		
			if last_report_error[2] >= 100:
				if local_only != True and settings.get("report_data", True):
					contents = open_url("http://127.0.0.1/?error=ERROR_LOOP|STUCK IN ERROR LOOP.")
				sleep(10)
				exit()
		else:
			# last error was more than 3 seconds ago, not stuck in a loop
			# reset timer and count
			last_report_error[0] = time.time()
			last_report_error[2] = 0
			
		for s in input:
			if hasattr(s, '__iter__')==True:
				message = message + '|'.join(ensure_string(x) for x in s) + "|"
			elif isinstance(s, basestring)==True:
				message = message + s + "|"
			else:
				message = message + "REPORT_ERROR|unknown input type|"
				try:
					message = message + ensure_string(s) + "|"
				except Exception as e:
					message = message + ensure_string(e) + "|"
				
		if message[-1]=="|":
			message = message[:-1]

		addmsg=""
		if last_played_video_source!=None:
			addmsg = "|Source|" + last_played_video_source
		print("#####: " + type + " :: " + message + addmsg)
		print("")

		if local_only != True and settings.get("report_data", True):
			contents = open_url("http://127.0.0.1/?error=" + urllib.quote_plus(ensure_string(type) + "|" + ensure_string(message)))
		
		last_report_error = [last_report_error[0], time.time(), last_report_error[2] + 1]
	except Exception as e:
		contents = open_url("http://127.0.0.1/?error=ERROR_REPORTING_ERROR|" + ensure_string(e))

def restart():
	"""Restarts the system using sudo shutdown command."""
	command = "/usr/bin/sudo /sbin/shutdown -r now"
	process = subprocess.Popen(command.split(), stdout=subprocess.PIPE)
	output = process.communicate()[0]
	print(output)

def returncleanASCII(s):
	"""
	Removes non-ASCII characters except '%', improving efficiency.
	
	:param s: The string to clean.
	:return: The cleaned string with only ASCII characters and '%'
	"""
	s = ensure_string(s)  # Ensure input is a string
	if not isinstance(s, str):
		report_error("RETURN_CLEAN_ASCII", ["Input is not a string"])
		return ""
	# Use a generator expression to filter out non-ASCII characters
	return s #"".join(c for c in s if 32 <= ord(c) <= 127 or c == '%')

def search_videos_within_range(file_list, start_num, end_num):
	"""
	Searches a list for file names containing %T(NUM)% where NUM is in range.
	
	:param file_list: List of file names to search.
	:param start_num: Starting number for the range.
	:param end_num: Ending number for the range.
	:return: List of file names that match the pattern and are within the range.
	"""
	if start_num is None or end_num is None:
		return file_list

	if start_num > end_num:
		report_error("SEARCH_VIDEOS", ["Invalid range: start_num > end_num", start_num, end_num])
		return []
	
	pattern = re.compile(r'%T\((\d+)\)%')
	matching_files = []

	for filename in file_list:
		match = pattern.search(filename)
		if match:
			num = int(match.group(1))  # Extract NUM
			if start_num <= num <= end_num:  # Check range
				matching_files.append(filename)

	return matching_files

def spread_division(total, parts):
	"""
	Distributes the total into parts as evenly as possible.
	Used by the commercial break generator to divide the total time into parts.

	:param total: Total value to be divided.
	:param parts: Number of parts to divide into.
	:return: List of integers representing the distributed values.
	"""
	base_value = total // parts  # Get the whole number portion
	remainder = total % parts  # Find the leftover value

	result = [base_value] * parts  # Start with equal distribution

	# Spread out the remainder across the first few elements
	for i in range(remainder):
		result[i] += 1
	random.shuffle(result)  # Shuffle the result to randomize the order
	return result

def update_current_time():
	"""
	Updates the global 'now' variable to the current date and time.
	Also checks if a test date is set in the settings and updates 'now' accordingly.

	:return: None
	"""
	global now
	global settings
	now = now_totheminute() # set the date time to the minute so seconds do not come into play when comparing times
	# check if the settings file has a test date 
	if 'time_test' in settings:
		if settings['time_test'] != None:
			# if it is, we set the global 'now' var to the test date
			# useful for testing holiday programming and other date/time specific programming
			now = datetime.datetime.strptime(str(settings['time_test']), '%b %d %Y %I:%M%p')

def update_settings():
	"""
	Updates the global settings variable by reading the settings file and validating its JSON format.

	:return: None
	"""
	global settings
	global settings_file
	
	f = open(settings_file, "r")
	# we need to verify that the settings file is JSON formatted.
	settings = validate_json(f.read())
	if settings == None:
		# report the error and exit script if it is not
		report_error("SETTINGS", ["settings file isn't valid JSON"])
		sleep(10)
		exit()

	f.close()

	settings['load_time'] = os.path.getmtime(settings_file)

	if 'version' in settings:
		if is_number(settings['version']):
			if float(settings['version']) != SETTINGS_VERSION:
				report_error("Settings", ["Settings version mismatch. Things might not work so well."])
		else:
			report_error("Settings", ["Settings version mismatch. Things might not work so well."])
	else:
		report_error("Settings", ["Settings version mismatch. Things might not work so well."])


def validate_json(json_str):
	"""
	Validates the JSON string and returns a dictionary or None if invalid.

	:param json_str: JSON string to validate.
	:return: Dictionary if valid, None if invalid.
	"""
	try:
		return json.loads(json_str, cls=ReferenceDecoder)
	except ValueError as err:
		return None

def weighted_random_choice(items, percentages):
	"""
	Selects a random item from a list based on the given percentages.

	Args:
	items (list): List of items to choose from.
	percentages (list): List of percentages corresponding to the items.

	Returns:
	A randomly selected item from the list.
	"""
	if len(items) != len(percentages):
		#raise ValueError("The number of items and percentages must match.")
		return None

	if sum(percentages) != 100:
		#raise ValueError("The percentages must add up to 100.")
		return None

	# Create a cumulative distribution
	cumulative_distribution = []
	cumulative_sum = 0

	for percent in percentages:
		cumulative_sum += percent
		cumulative_distribution.append(cumulative_sum)

	# Generate a random number between 0 and 100
	random_number = random.uniform(0, 100)

	# Select the item based on the random number and cumulative distribution
	for i, threshold in enumerate(cumulative_distribution):
		if random_number <= threshold:
			return items[i]

def wildcard_array():
	"""
	Returns a list of wildcard options for the settings file.

	:return: List of wildcard options.
	"""
	return ['all', 'any', '*']

def generate_bumpers_list(bumpers_in, bumpers_out, total, in_chance=1.0, out_chance=1.0):
	"""
	Generates a list of in and out bumpers for each commercial break.
	
	:param bumpers_in[]: A list of paths for the in bumpers.
	:param bumpers_out[]: A list of paths for the out bumpers.
	:param total: The total number of commercial breaks.
	:param in_chance: The chance of an in bumper being played (0.0 to 1.0). Defaults to 1.0 (always play).
	:param out_chance: The chance of an out bumper being played (0.0 to 1.0). Defaults to 1.0 (always play).

	:return: A list of paths that point to the bumbers to be played.
	"""

	if bumpers_in == None and bumpers_out == None:
		return None

	in_total = 0
	out_total = 0

	bin = []
	bout = []
	tin = []
	tout = []

	for item in bumpers_in:
		if os.path.exists(item):
			tin += get_videos_from_dir_cached(item)

	for item in bumpers_out:
		if os.path.exists(item):
			tout += get_videos_from_dir_cached(item)

	for i in range(1, total):
		if len(tin) > 0:
			if eval_equation(in_chance, now) >= random.random():
				temp = random.choice(tin)
				in_total += get_length_from_file(temp)
				bin.append(temp)

		if len(tout) > 0:
			if eval_equation(out_chance, now) >= random.random():
				temp = random.choice(tout)
				out_total += get_length_from_file(temp)
				bout.append(temp)

	printd(["BUMPERS: IN: ", bin,"OUT: ", bout])
	return { "in" : bin, "out": bout, "in_total": in_total, "out_total": out_total }


# global variables

channel_name_static = None							# the name of the current static programming block
now = datetime.datetime.now() 						# set the date time (will be updated in the main loop using update_current_time())
last_played_video_source = None 					# the last video played

script_load_time = time.time()						# the time the script was loaded, for logging uptime

curr_static = None 									# the current static programming block
start_time = time.time()							# the time the last video was played, used to determine if we are in error channel mode amoung other things
error_channel_set = False							# if we are in error channel mode, this will be set to True
current_video_tag = None							# the current video tag, can be set in the settings file or by specific files to trigger commercial programming

last_video_played = ""								# the last video played
last_error = (7.0,'',0)								# the last error reported, used to determine if we are in a loop or not

base_directory = os.path.dirname(__file__) 			# the base directory of the script
if base_directory:									# if the base directory is not empty, we add a trailing slash to it
    base_directory = base_directory.rstrip("/") + "/"

# /global variables

# settings

settings = None 									# the global settings variable
settings_file = base_directory + "settings.json"	# the settings file path

update_settings() 									# load the settings file, validate it, and store it in the global "settings" variable

# /settings

allow_chance = True 								# We need to decided if we should allow a chance of a video to play as set by our programming in the settings file
source = None 										# the source of the video to play, set by the programming schedule
folder = None 										# the folder where the video is located, set by the programming schedule, where video files are located

# main loop

report_error("STARTUP", ["Script is now running!"]) # report that the script has started

while(1): # main loop
	try:

		if (time.time() - start_time) > 59 and error_channel_set: # if we are in error channel mode and more than 59 seconds have passed
			# if we are in error channel mode, we will not exit the script
			report_error("MAIN_LOOP", ["Error channel was set but no video is playing.|Check settings.json file for proper error channel.|Make sure the directory at which error channel points exists and has at least one video file.|Since there is no video to play, the script will now exit."])
			exit()
	
		if (time.time() - start_time) > 59: # if the script has been running for more than 1 minute
			report_error("MAIN_LOOP", ["No Video Available to Play", "Check things like TV Schedule names misspelled or erroneously added.", "Entering Error Channel mode (if set, check settings.json), otherwise the script will end now."])
			if get_setting(['channels', 'error']): # if the error channel is set in the settings file, attempt to play videos from that programming block
				channel_name_static = settings['channels']['error']
				error_channel_set = True
				start_time = time.time()
			else: # if no videos have been played in the last 3 minutes, it likely means there is an error in the Error programming block, exit the script
				report_error("MAIN_LOOP", ["No videos are playing and no error channel is set in the settings file.", "The script will now exit."])
				exit()
				
	
		last_played_video_source = None
		if os.path.getmtime(settings_file) != settings['load_time']:
			update_settings()
			report_error("SETTINGS", ["Settings Updated"])

		# set a minimum of 5 commercial breaks if is not set in the settings file
		if 'commercials_per_break' not in settings: settings['commercials_per_break'] = 5

		update_current_time() # update the current time to the current date and time, or test date if set in the settings file

		if 'time_test' in settings: # check if a test date is set in the settings file
			if settings['time_test'] != None: # if it is set, we will use the test date instead of the current date and time
				report_error("TEST_TIME", ["Using Test Date Time " + ensure_string(now)])

		# break down the date and time for ease of use
		month = now.month
		h = now.hour
		m = now.minute
		d = now.weekday()
		ddm = now.day

		folder = None					# the main folder(s) the current video will be pulled from
			
		# check if a static programming block has been set
		if curr_static == None: # there is no static block
			# checks the current date/time against the programming schedule in the settings file
			
			update_settings()
			programming_schedule = check_video_times(settings['times'], get_current_channel(), allow_chance)
			if programming_schedule == None:
				report_error("PROGRAMMING_SCHEDULE", [ "The programming schedule returned no results.", "Check your settings file to ensure programming is set for this time.", "Entering Error Channel mode (if set, check settings.json), otherwise the script will end now." ])
				if get_setting(['channels', 'error']):
					channel_name_static = settings['channels']['error']
					error_channel_set = True
					continue
				else:
					exit()
			
			if programming_schedule[3] != None:
				if programming_schedule[3][0] == True:
					# the schedule has returned a static choice.
					# this blocks off a certain amount of time to retain the chosen schedule
					programming_schedule[3][1] = time.mktime(now.timetuple()) + float(programming_schedule[3][1]) # set the cut off time from NOW + the time designated by settings
					curr_static = programming_schedule # use the current programming block for future refence until NOW > time set by settings
					channel_name_static = programming_schedule[3][2]
					report_error("STATIC", ["static set", programming_schedule[3][2]])
		else: # there is a static block
			#check if the static block allotted time has passed
			if time.mktime(now.timetuple()) > curr_static[3][1]:
				curr_static = None
				channel_name_static = None
		
			# set current programming block to the static one
			# if the static channel has ended, programming schedule will be empty and the loop will skip it
			programming_schedule = curr_static
			printd('its static time')
		
		# sets an array if paths where video should be
		printd(programming_schedule)
		folder = programming_schedule[0]

		printd('Selected Folder: ', str(programming_schedule[2]) + " - " + replace_all_special_words(folder[0]))
		if folder != None: # folders have been set, load and play video
			
			# going to loop through all returned directories, replacing special keywords and validating that the directory does exist
			new_folder=[] # temp list of new directories
			for i in range(0, len(folder)):
				tmp_folder = replace_all_special_words(folder[i]) # replace special keywords
				if os.path.isdir(tmp_folder) == False: # folder doesn't exist
					report_error("MAIN_LOOP", [folder, "directory does not exist", "check settings.json file"]) # report error that directory exist
				else:
					new_folder.append(tmp_folder) # add directories that do exist

			folder = new_folder # replace old folders with the newly verified folders
			
			video = None # the list of videos that can be selected from

			if programming_schedule[2] == "video" or programming_schedule[2] == "video-show" or programming_schedule[2] == "commercial": # just select a random video out of the path
				# settings wants the random video to be selected from multiple folders but prefers the random video come from the folders supplied rather than the combine contents of each folder
				# this can help balance the show played when supplying different shows where a show with more videos would be more randomly favored

				# check if min-length or max-length are set, if not, set them to 0 and 99999 respectively
				min_len = programming_schedule[4].get('min-length', 0)
				max_len = programming_schedule[4].get('max-length', 99999)

				for key, val in [('min-length', min_len), ('max-length', max_len)]:
					# check to make sure min-length and max-length are numbers
					if not is_number(val): # if they are not numbers, report an error
						report_error("PROGRAMMING", [ key + " is set but it is not a number", "check settings.json file", programming_schedule[4] ])

				folder_chance = None 
				if 'prefer-folder' in programming_schedule[4]:
					folder_chance = programming_schedule[4]['prefer-folder']
				

				if folder_chance != None: # check to see if the user has set a folder preference
					if 'weighted' in programming_schedule[4]: # check to see if the user has set a weighted folder selection
						if len(programming_schedule[4]['weighted']) != len(folder): # check to see if the weighted folder count matches the folder count
							report_error("GET_RND_COMM", ["Weighted values supplied do not match folder count", "check settings.json file", programming_schedule[4]]) # report error if they don't match
							rfolder = random.choice(folder) # if they don't match, just select a random folder
						else:
							rfolder = weighted_random_choice(folder, programming_schedule[4]['weighted']) # select a folder based on the weighted values
							if rfolder == None:
								report_error("GET_RND_COMM", ["Weighted folder selection failed", "check settings.json file", programming_schedule[4]]) # report error if the weighted values are invalid
					else:
						rfolder = random.choice(folder) # if no weighted values are set, just select a random folder
					video = get_videos_from_dir_cached(rfolder, min_len, max_len) # get the videos from the selected folder
					print("Selecting video from: " + rfolder) # print the selected folder
					print("")
				else:
					print("Selecting video from: ", folder) # print the selected folder
					print("")
					video = get_videos_from_dir_cached(folder[0], min_len, max_len) # get the videos from the first folder in the list
					for itemX in range(1,len(folder)):
						video = video + get_videos_from_dir_cached(folder[itemX], min_len, max_len) # add the videos from the folder to the list of videos

				if len(video) == 0:
					report_error("PROGRAMMING", ["video folder contains no videos after checking min-length and max-length"])
					continue

			elif programming_schedule[2] == "balanced-video": # select a random video from a single directory balanced around play count
				url=""
				for itemX in range(0,len(folder)):
					url = url + "&f" + ensure_string(itemX+1) + "=" + urllib.quote_plus(ensure_string(folder[itemX]))
				urlcontents = open_url("http://127.0.0.1/?get_next_rnd_episode_from_dir=1" + url)

				printd("BV-Response: ", urlcontents, "BV-Sent", "http://127.0.0.1/?get_next_rnd_episode_from_dir=1" + url)
				acontents = urlcontents.split("|") #split the response, the next episode will be the first result
				try:
					if(acontents[1]=="0" or "|" not in urlcontents):
						#could not find the next episode, so we should just report the error and let a random video be selected below
						video = [ random.choice(get_videos_from_dir_cached(rfolder)) ]
						report_error("SHOW BALANCED VIDEO", acontents)
					else:	
						# add the returned video as an array, if the file exists, so it can be selected randomly (but it's the only video that can be selected) during the next step
						if(os.path.exists(acontents[0])):
							video = [ acontents[0] ]
						else:
							# if the file doesn't exist for whatever reason, report error and let a random video be selected below
							report_error("SHOW BALANCED VIDEO", [ "File does not exist ", acontents[0], "Did you rename the file?", "Check the database for renamed files." ])
				except:
					report_error("SHOW BALANCED VIDEO", [ urlcontents ])
				
			elif programming_schedule[2] == "ordered-video": # select a random video but
				video = get_videos_from_dir_cached(folder[0])
				for itemX in range(1,len(folder)):
					video = video + get_videos_from_dir_cached(folder[itemX])

				selvideo = random.choice(video) # choose a random video from the directory to use as a reference (needs to be random in case there are multiple different directories)
				urlcontents = open_url("http://127.0.0.1/?get_next_episode=" + urllib.quote_plus(ensure_string(selvideo)))
				printd("OV-Response: ", urlcontents, "OV-Sent", selvideo)
				acontents = urlcontents.split("|") #split the response, the next episode will be the first result
				
				try:
					if(acontents[1]=="0" or "|" not in urlcontents):
						#could not find the next episode, so we should just report the error and let a random video be selected below
						video = [ selvideo ]
						report_error("SHOW VIDEOS IN ORDER", acontents)
					else:	
						# add the returned video as an array, if the file exists, so it can be selected randomly (but it's the only video that can be selected) during the next step
						if(os.path.exists(acontents[0])):
							video = [ acontents[0] ]
						else:
							# if the file doesn't exist for whatever reason, report error and let a random video be selected below
							report_error("SHOW VIDEOS IN ORDER", [ "File does not exist ", acontents[0] ])
				except:
					report_error("SHOW VIDEOS IN ORDER", [ urlcontents ])
			
			elif programming_schedule[2] == "ordered-show": # select random directory from path for reference and then play the episodes in ascending order
				# get a list of folders from the local server
				# http://127.0.0.1/?getavailable=win_Tuesday&dir=/media/pi/ssd_b/primetime/win_tuesday
				folders = None
				#folders = get_folders_from_server(get_videos_from_dir_cached(get_folders_from_dir(replace_all_special_words(folder[0]))[0])[0], replace_all_special_words(folder[0]))

				# prepare the folder name
				cleaned_folder = replace_all_special_words(folder[0])
				# get the first folder from the directory
				first_local_folder = get_folders_from_dir(cleaned_folder)[0]
				# get cached videos from that folder
				first_video_folder = get_videos_from_dir_cached(first_local_folder)[0]
				# get_folders_from_server() requires a video file and the directory name to determine which shows should be used to select the next video from
				folders = get_folders_from_server(first_video_folder, cleaned_folder)

				# if the server returns no folders, default to all directories in path
				if folders == None:
					folders = get_folders_from_dir(replace_all_special_words(folder[0])) # returns all subfolders of a directory
				
				selfolder = random.choice(folders) # choose a random folder from the list returned by the server
				episodes_in_folder = get_videos_from_dir_cached(selfolder) # preload all the files in that directory
				if len(episodes_in_folder) <= 0:
					report_error("FOLDERS", [ "Issue selecting video from folder.", "Is the folder empty?", selfolder, "Entering Error Channel mode (if set, check settings.json), otherwise the script will end now." ])
					if get_setting(['channels', 'error']):
						channel_name_static = settings['channels']['error']
						error_channel_set = True
						continue
					else:
						exit()
				# to get the next video from the PHP front end we need to pass one of the episode's file names so it can determine the "short name"
				urlcontents = open_url("http://127.0.0.1/?get_next_episode=" + urllib.quote_plus(ensure_string(episodes_in_folder[0])))
				printd("OS-Response: ", urlcontents, "OS-Sent", episodes_in_folder[0])
				acontents = urlcontents.split("|") #split the response, the next episode will be the first result
					
				try:
					if(acontents[1]=="0" or "|" not in urlcontents):
						#could not find the next episode, so we should play the first episode
						#report_error("SHOW EPISODES IN ORDER", acontents)
						video = [ episodes_in_folder[0] ]
					else:	
						# add the returned video as an array, if the file exists, so it can be selected randomly (but it's the only video that can be selected) during the next step
						if(os.path.exists(acontents[0])):
							video = [ acontents[0] ]
						else:
							# if the file doesn't exist for whatever reason, play a random episode from that folder.
							report_error("ORDERED-SHOW", [ "File does not exist ", acontents[0], "Playing a random episode from the folder instead.", "Check for things like renamed files." ])
							video = episodes_in_folder
				except:
					report_error("ORDERED-SHOW", [ urlcontents ])
			elif programming_schedule[2] == "show": # select random directory from path and then play a random video
				folders = get_folders_from_dir(replace_all_special_words(folder[0])) # returns all subfolders of a directory
				selfolder = random.choice(folders) # choose a random one
				video = get_videos_from_dir_cached(selfolder) # preload all the files in that directory
			else:
				report_error("Programming Schedule", [ "unknown type set for video", programming_schedule[2], "check settings.json file", "Entering Error Channel mode (if set, check settings.json), otherwise the script will end now." ])
				if get_setting(['channels', 'error']):
					channel_name_static = settings['channels']['error']
					error_channel_set = True
					continue
				else:
					exit()

			if video != None:
				if len(video) == 0:
					report_error("PLAY", ["video folder contains no videos", "SOURCE", ensure_string(folder[0]),"SELECTED", ensure_string(selfolder), "PROGRAMMING:", programming_schedule[0], programming_schedule[1], programming_schedule[2]])
			
			# if we managed to find some videos, we can proceed	
			if video:
				# check if minimum and maximum length for the video to be played

				# we can now select our random video from the folder(s)
				source = random.choice(video)

				# log it if there's no source file
				if source==None:
					report_error("PLAY", ["no source video", programming_schedule[0], programming_schedule[1], programming_schedule[2]])
					continue

				if 'set-tag' in programming_schedule[4]:
					# if a tag is set in the programming schedule, we use that
					current_video_tag = programming_schedule[4]['set-tag']

				# get the tag from the video file name.
				source_tag = get_video_tag(source)
				if source_tag: # if a tag is found in the file name, it overrides any tag set in the programming schedule
					current_video_tag = source_tag # set the current video tag to the tag found in the file name

				# load commercial break time stamps for this video (if any)
				commercials = get_commercials(source)
		
				acontents = ["default","0","values"]
				bumpers = None

				# skip this video if it has been played recently and try again
				if acontents[1]!="0":
					print("Just played this show, skipping")
					print("")
					allow_chance = False # we were not successful in selecting a video (because this last one was recently played), so we won't take a chance on a different video. we'll try again on playing a non-chanced video
				else:
					# now that we have a video to play, we can generate the bumpers and commercials

					allow_chance = True # allow random content again

					# if commercials are not set to be inserted, we set the commercials per break to 1 so that no commercials are inserted
					if not settings['insert_commercials']: settings['commercials_per_break'] = 1 

					pregenerated_commercials_list = None # the pregenerated commercials list variable
					commercials_per_break = settings['commercials_per_break'] # the number of commercials to play per break
					source_total_length = get_length_from_file(source) # get video duration from file name 
					# if commercials per break is set to "auto", we need to calculate how many commercials to play based on the length of the video and the current time
					if settings['commercials_per_break'] == "auto" and programming_schedule[2] != "commercial" and len(commercials)>0:
						source_dir = os.path.dirname(source) # get the directory of the source video

						# define variables for bumpers
						bumpers_in = []
						bumpers_out = []
						bumpers_in_chance = 1.0
						bumpers_out_chance = 1.0
						bumpers = None

						# check if there are bumpers in the source directory
						if os.path.isdir(source_dir + "/bumpers/in"): # if there is a bumpers/in directory, we use that
							bumpers_in.append(source_dir + "/bumpers/in")
						if os.path.isdir(source_dir + "/bumpers/out"): # if there is a bumpers/out directory, we use that
							bumpers_out.append(source_dir + "/bumpers/out")

						if 'bumpers' in programming_schedule[4] and not os.path.isfile(source_dir + "/bumpers/skip"): # check if bumpers are set in the programming schedule (can be overridden by a 'skip' file in the source directory)
							bumpers_in_mix = programming_schedule[4]['bumpers'].get('show-override', {}).get('in') is True # check if bumpers IN should be mixed with the source directory bumpers
							bumpers_in_chance = programming_schedule[4]['bumpers'].get('chance', {}).get('in', "1.0") # get the chance of bumpers IN being used, default to 100%
							# if bumpers IN are set in the programming schedule, we use those
							# unless bumpers are set in the source directory or the settings allow mixing is set
							if 'in' in programming_schedule[4]['bumpers'] and (not os.path.isdir(source_dir + "/bumpers/in") or bumpers_in_mix):
								for item in programming_schedule[4]['bumpers']['in']:
									temp = replace_all_special_words(item)
									if os.path.isdir(temp): # if it's a directory, we add the directory
										bumpers_in.append(temp)
									else: # if it's not a directory, we report an error
										report_error("BUMPERS", ["bumpers 'IN' directory is set in settings file but it does not exist", "SOURCE", ensure_string(source), "BUMPERS IN", ensure_string(temp)])

							bumpers_out_mix = programming_schedule[4]['bumpers'].get('show-override', {}).get('out') is True
							bumpers_out_chance = programming_schedule[4]['bumpers'].get('chance', {}).get('out', "1.0")


							printd("out? ", 'out' in programming_schedule[4]['bumpers'], "bump dir? ", not os.path.isdir(source_dir + "/bumpers/out"), "bump mix? ", bumpers_out_mix, "chance? ",bumpers_out_chance, "chance eval: ", eval_equation(bumpers_out_chance, now))
							# if bumpers OUT are set in the programming schedule, we use those unless bumpers are set in the source directory or the settings allow mixing is set
							if 'out' in programming_schedule[4]['bumpers'] and (not os.path.isdir(source_dir + "/bumpers/out") or bumpers_out_mix):
								for item in programming_schedule[4]['bumpers']['out']:
									temp = replace_all_special_words(item)
									if os.path.isdir(temp): # if it's a directory, we add the directory
										bumpers_out.append(temp)
									else: # if it's not a directory, we report an error
										report_error("BUMPERS", ["bumpers 'OUT' directory is set in settings file but it does not exist", "SOURCE", ensure_string(source), "BUMPERS OUT", ensure_string(temp)])
						
						if bumpers_in or bumpers_out:
							bumpers = generate_bumpers_list(bumpers_in, bumpers_out, len(commercials) + 1, bumpers_in_chance, bumpers_out_chance) # preload the bumpers
							printd("BUMPERS:", bumpers)
							#readkey()

						if source_total_length == None: # video length was not found, so we revert to random commercials without checking for time
							commercials_per_break = 0
							report_error("COMM_BREAK", ["length of video could not be found", "SOURCE", ensure_string(source)])
						else:
							# calculate the time difference between the video length and the time until the next hour/half hour
							source_length_diff = calculate_fill_time(source_total_length, now, get_setting([ "commercials_offset_time" ], 0)) # calculate the time difference between the video length and the current time
							printd("length:", source_total_length,  "diff to make up:", source_length_diff, "current time:", month, h, m, d, ddm)

							if(bumpers != None):
								# if bumpers are set, we need to calculate the total length of the bumpers and subtract it from the total time to fill
								source_length_diff = source_length_diff - (bumpers['in_total'] + bumpers['out_total'])
								printd("Total time to fill:", source_length_diff, "bumpers in total:", bumpers['in_total'], "bumpers out total:", bumpers['out_total'])
								#readkey()

							# generate a list of commercials to play based on the length of the video and difference in time
							pregenerated_commercials_list = generate_commercials_list(source_length_diff, get_setting([ "commercials_fill_time_multiplier" ], 50))

							commercials_per_break = 0
					elif programming_schedule[2] == "commercial": # programming schedule is set to "commercial"
						commercials_per_break = 0 # we don't want to play commercials during a commercial ;-)
						report_video_playback(source, "commercial") # let the server know that commercial is played (usually handled by the main play_video function but this is a special case)
					else:
						if is_number(settings['commercials_per_break']) == False:
							# if commercials_per_break should be set to 'auto' or a NUMBER. If it's not a number, we assume it should be set to auto but there might not be a commercials file available for this video, so we schedule no commercials for this video.
							commercials_per_break = 0 # set to 0 because a number is expected but something is amiss
							if len(commercials)>0:
								# if the commercials file exists, then something else went wrong and we report it
								report_error("COMM_BREAK", ["commercials per break is not a number", "If set to 'auto', make sure the video's filename contains the video's length %T(0000)%", "Check that a .commercials file exists with commercial time stamps", "FILE", ensure_string(source)])
						else:
							commercials_per_break = settings['commercials_per_break'] - 1 # subtract 1 because humans are dumb and count from 1, computers are smart so count from 0

					if is_number(commercials_per_break) == False: # check to see if commercials_per_break is a number (it should be at this point)
						# something is really borked if we are here
						commercials_per_break = 0 # set to 0 because a number is expected but something is amiss
						report_error("COMM_BREAK", ["commercials per break was not a number, it should be!"]) # report error if it's not a number

					print('Attempting to play: ', source) # Announce the video to be played to the console
					print("")
					printd("PLAY INFO", [str(now), programming_schedule]) # print the programming schedule for this video to the console for debugging purposes
					
					log_only_latest = programming_schedule[4].get('log only latest', -1) # check if we should only log the latest video played in the programming schedule
					if not is_number(log_only_latest):
						log_only_latest=-1

					play_video(source, commercials, commercials_per_break if pregenerated_commercials_list == None else pregenerated_commercials_list, 0, bumpers, log_only_latest)

					start_time = time.time() # reset the start time to now since we successfully played a video

					if error_channel_set == True: # if we were in error channel mode and successfully played a video, we exit error channel mode
						report_error("MAIN_LOOP", ["Exited Error Channel mode, video played successfully"])
						error_channel_set = False
						channel_name_static = None

				print("-------------------------------------------------------------------------------")
				# send an uptime (time since last restart) tick to the web server
				report_error("UPTIME", [str(get_uptime(script_load_time))])
				# reboot the pi after playing a video in the early AM.
				try:
					curr_dt_now = datetime.datetime.now()
					if (curr_dt_now.hour >= 2 and curr_dt_now.hour <= 5 and get_uptime(script_load_time) > 36000):
						report_error("RESTART", ["success"])
						sleep(0.5)
						restart()
				except Exception as e:
					report_error("RESTART", ["failed"])

		else:
			printd('no folder')
	except Exception as valerr:
		report_error("MAIN_LOOP", [valerr, source, folder, traceback.format_exc()])