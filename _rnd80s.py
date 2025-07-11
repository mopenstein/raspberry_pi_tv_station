#!/usr/bin/python

# version: 102.0
# version date: 2025.xx.x
#	'chance' setting now supports mathematical equations
#	New settings added "cache_path" which is used to define the path to the cache directory.
#	New settings added "commercials_offset_time" which is used in the calculation of the remaining time to fill when determining how much time is needed to fill the video to the next half-hour or top-of-the-hour mark.
#	New settings added "commercials_fill_time_multiplier" which sets the multiplier in the timeout logic that is used when generating commercials to fill remaining time.
#
#	New programming block setting added: "set-tag"
#		This allows you to set a tag for the video that is being played. The tag can be used to trigger commercials programming based on the tag.
#
#	Added fathers day and memorial day to special days list
#
# settings version: 0.993
#
#	Base setting added: "cache_path"
#		Use this setting to specify the path to the cache directory. Default is "cache" in the same directory as this script.
#
#	Base setting added: "commercials_offset_time" 
#		Use this if your videos aren't starting exactly at the top of the hour or half hour. The preciseness of the calculated time may be too accurate
# 		 and, due to real world execution, might delay the next video resulting in videos starting a minute too late. Use positive if your shows are starting too early and negative if starting too late.
#		Example:
#			"commercials_offset_time": -30
#
#	Base setting added: "commercials_fill_time_multiplier" 
#		Use this if you find that not enough commercials are being generated to fill the time. Default is 20. The longer the duration of the video, the more commercials will need to be generated.
# 		 The multiplier is used to determine the maximum time allowed to generate commercials. A shorter video will need less time to generate commercials, while a longer video will need more time.
#		Example:
#			"commercials_offset_time": 50
#
#	Programming block setting added: "set-tag"
#		Use this setting to set the current tag. Commercials programming can be triggered based on the tag.
#		Example:
#
#		"times": [{
#			"name": ["%D[1]%/movies"],
#			"between": { "times": [ ["12:00AM", "03:45AM"] ] },
#			"type": "balanced-video",
#			"set-tag": "movies"
#		}]
#------------------------------------------------------------------------------------------
#		"commercial_times": [{
#			"name": ["%D[1]%/commercials/movies"],
#			"tag": "movies"
#		}]
#
#	Programming setting expanded: "min-length" and "max-length"
# 		These can now be set in commercials programming blocks as well as in video programming blocks. Programming blocks are limited those identified as "commercials", "video", or "video_show" type
#
#	Programming setting expanded: "chance"
#		"chance" can now be set to a mathematical equation that will be evaluated and the result will be used to determine if the programming block should be triggered.
#
#		Example:
# 		"chance": "clamp((4 - abs(hour - 8)) / 3.0 * (weekday in [5,6]) * .8)"
#
#	 	This example of chance starts ramping up at 5 AM, peaks around 8 AM, and fades by 12 PM on Saturday and Sundays only.
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

GET_VIDEOS_FROM_DIR_MIN_DURATION = 0 	 	# default minimum duration of a video in seconds when returning videos from a directory
GET_VIDEOS_FROM_DIR_MAX_DURATION = 99999 	# default maximum duration of a video in seconds when returning videos from a directory

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

last_played_video_source = None # the last video played

def play_video(source, commercials, max_commercials_per_break, start_pos):
	"""
	Play a video file using OMXPlayer on the Raspberry Pi.
	
	This is the main video playing experience.

	:param source: The path to the video file to be played.
	:param commercials: A list of commercial break times in seconds.
	:param max_commercials_per_break: The maximum number of commercials to play during a break.
	:param start_pos: The position in seconds to start the video from.
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

	global settings # settings file
	if source==None: # if no video file was supplied, we're done here
		return
	err_pos = 0.0
	try:
		global last_played_video_source
		global error_count

		current_position = 0
		gend_commercials = None
		
		if type(max_commercials_per_break) == list: #if a list of commercials was passed instead of a number, set variables
			gend_commercials = max_commercials_per_break
			if len(gend_commercials)!=0 and len(commercials)!=0:
				max_commercials_per_break = spread_division(len(gend_commercials), len(commercials))
			print("MAXCOMM: " + ensure_string(max_commercials_per_break))
		else:
			if max_commercials_per_break>0:
				#if we're inserting commercials, let's make sure we can find some
				comm_source = get_random_commercial()
				if comm_source == None: 
					#couldn't find a commercial, so we won't even try to play any during the current video but we should report the error
					max_commercials_per_break = [] #setting max commercials to 0, overrides the passed value and disables commercials during this video
		comm_player = None
		
		print('Main video file:' + ensure_string(source))
		contents = open_url("http://127.0.0.1/?current_video=" + urllib.quote_plus(ensure_string(source)))
		sleep(0.5)
		player = OMXPlayer(source, args=settings["player_settings"], dbus_name="omxplayer.player" + str(random.randint(0,999)))
		sleep(0.5)
		#player.set_aspect_mode('stretch')
		player.pause()
		player.play()
		lt = 0
		
		player.seek(start_pos)
		
		while (1):
			err_pos = 0.0
			last_played_video_source = source
			try:
				position = player.position()
				current_position = position
			except:
				break
			
			try:
				#check to see if commercial times were passed and also check to see if the max commercials allowed is greater than 0
				if len(commercials) > 0 and len(max_commercials_per_break)>0:
					#found a commercial break, play some commercials
					if float(position)>=float(commercials[0]) and position>0:
						#remove the currently selected commercial time from list of positions
						commercials.pop(0)
						#pause and hide the main video player
						try:
							player.hide_video()
							player.pause()
						except:
							print("player hide/pause error")
						
						sleep(0.5)
						#set local variable to the passed maximum amount of commercials per break allowed, so we can countdown to 0
						#comm_i = max_commercials_per_break
						comm_i = max_commercials_per_break[0]-1
						max_commercials_per_break.pop(0)
						#loop to play commercials until we've played them all
						while(comm_i>=0):
							try:
							
								#get a random commercial and report it to the webserver for stats
								if gend_commercials == None:
									#user has set a specific number of commercials per break
									comm_source = get_random_commercial()
									if comm_source==None:
										report_error("PLAY_COMM", ["could not get a random commercial"])
										continue
								else:
									#user has chosen to automatically generate the amount of commercials
									if len(gend_commercials) == 0:
										break
									else:
										comm_source = gend_commercials.pop(0)
										print("Commercials remaining:", len(gend_commercials))
								last_played_video_source = comm_source
								print('Playing commercial #' + str(comm_i), comm_source)
								contents = open_url("http://127.0.0.1/?current_comm=" + urllib.quote_plus(ensure_string(comm_source)))
								#load commercial
								if comm_player == None:
									comm_player = OMXPlayer(comm_source, args=settings["player_settings"], dbus_name="omxplayer.player1")
									comm_player.set_video_pos(0,0,680,480)
									comm_player.set_aspect_mode('stretch')
								else:
									comm_player.load(comm_source)
								#comm_player.pause()
								
								sleep(0.5)
							
								try:
									comm_player.show_video()
								except:
									print("comm_player show error")
								
								#play commercial
								comm_player.play()

								#we need to wait until the commercial has completed, so we'll check for the current position of the video until it triggers an error and we can move on
								comm_length = get_length_from_file(comm_source) 	# get the length of commercial being played
								comm_start_time = time.time()						# get current time stamp
								comm_end_time = comm_start_time + comm_length + 1	# calculate at what time the commercial should have ended plus a little buffer (1 second)
								
								while (1):
									if time.time() > comm_end_time: # commercial should be over by now, so we move on
										break
										
									try:
										#if we can't get the current position of the commercial, we should break out of the loop and move on
										comm_position = math.floor(comm_player.position())
									except:
										break
									
									#sometimes the main player doesn't hide/pause. we should make sure that it does
									try:
										if player.is_playing() == True:
											player.hide_video()
											player.pause()
									except:
											print("player hide/pause error")
							except Exception as exce:
								#if there was an error playing commercials, report it and resume playing main video
								error_count = error_count + 1
								report_error("COMM_PLAY_LOOP", ["error", ensure_string(exce), "SOURCE", comm_source, traceback.format_exc()])
							
							#decrement the amount of remaining commercials
							comm_i = comm_i - 1
							sleep(.5)
							
						#show and resume the currently playing main video after the commercial break
						player.show_video()
						player.play()
						
			except Exception as ecce:
				#if there was an error playing commercials, report it and resume playing main video
				error_count = error_count + 1
				report_error("COMM_PLAY", ["Error", ensure_string(ecce), "SOURCE", comm_source, traceback.format_exc()])
				player.show_video()
				player.play()


		err_pos = 7.0
		#main video has ended
		player.hide_video()
		sleep(0.5)
	except Exception as e:
		if(err_pos!=7.0):
			error_count = error_count + 1
			report_error("PLAY_LOOP", ["Error", ensure_string(e), "SOURCE", ensure_string(source), traceback.format_exc()])
		
		#kill all omxplayer instances since there was problem with the main video.
		kill_omxplayer()
		try:
			if comm_player != None:
				comm_player.quit()
		except Exception as ex:
			print("error comm quit " + ensure_string(ex))
		try:
			if player != None:
				player.quit()
		except Exception as exx:
			print("error player quit " + ensure_string(exx))
			
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
		report_error("PLAY_quit",["Position", ensure_string(err_pos), "Error", ensure_string(ex)])
	
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
			return -1

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
            "scale": lambda x: float(x) / 100,
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

		return eval(equation.lower().replace('_',''), safe_globals, {})
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

# Function to check if the current date and time fall within the ranges
def is_within_range(data):
	"""
	Checks if the current date and time fall within the specified ranges.

	:param data: A dictionary containing date, time, and year ranges.
	:return: True if the current date and time fall within the ranges, False otherwise.
	"""
	global now
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

			start_time = datetime.datetime.strptime(start_time_str, "%I:%M%p")
			end_time = datetime.datetime.strptime(end_time_str, "%I:%M%p")
			if start_time.time() <= current_datetime.time() <= end_time.time():
				time_within_range = True
				break
		else:
			start_time_str = replace_special_words(time_range)
			start_time = datetime.datetime.strptime(start_time_str, "%I:%M%p")
			if start_time.time() == current_datetime.time():
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
		update_current_time()
		global now
		global current_video_tag

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
				#print(timeItem['name'], str(stime), now, ntime, etime)
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
	print("Max time:", max_time_before_timeout)
	remaining_time = total_time_seconds
	selected_intervals = []
	start_time = time.time()
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
		
		if time.time() - start_time > max_time_before_timeout:
			print("taking too long to generate commercials, stopping", remaining_time)
			break
		if remaining_time < 20:
			print("Remaining time:", remaining_time)
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
	Returns a list of commercials from the specified source video file.

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
				
				video = get_videos_from_dir(replace_all_special_words(rfolder), min_len, max_len) # get the videos from the selected folder
			else: # no folder preference set
				video = get_videos_from_dir(replace_all_special_words(folder[0]), min_len, max_len) # get the videos from the first folder in the list
				for itemX in range(1,len(folder)): # loop through the rest of the folders
					video = video + get_videos_from_dir(replace_all_special_words(folder[itemX]), min_len, max_len) # add the videos from the folder to the list of videos
			
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

VIDEO_EXTENSIONS = ('mp4', 'avi', 'webm', 'mpeg', 'm4v', 'mkv', 'mov', 'flv', 'wmv')

# Maybe one day we can use this to cache the results of get_videos_from_dir
# but for now, we will just use the cache file to store the filenames
#
# 	_cached_get_videos_from_dir = {}
#	_cached_get_videos_from_dir[dir_path] = filtered_results_full_paths
#

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
		cache_mtime = os.path.getmtime(cache_fname)
		dir_mtime = os.path.getmtime(dir_path)

		if cache_mtime >= dir_mtime:
			with open(cache_fname, 'r') as f:
				all_filenames = json.load(f)
		else:

			# Cache is outdated, rescan and update cache
			filenames_from_scan = []
			for ext in VIDEO_EXTENSIONS:
				for full_file_path in glob.glob(os.path.join(dir_path, '*.{}'.format(ext))):
					filenames_from_scan.append(os.path.basename(full_file_path))
			
			with open(cache_fname, 'w') as f:
				json.dump(filenames_from_scan, f)
			all_filenames = filenames_from_scan
	else:
		# Cache does not exist, rescan and create cache
		filenames_from_scan = []
		for ext in VIDEO_EXTENSIONS:
			for full_file_path in glob.glob(os.path.join(dir_path, '*.{}'.format(ext))):
				filenames_from_scan.append(os.path.basename(full_file_path))
		
		with open(cache_fname, 'w') as f:
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

def get_uptime():
	"""	Returns the uptime of the script in seconds. """
	global script_start_time
	return (time.time() - script_start_time)

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
		global settings
		
		print(url)
		if settings["report_data"] == False:
			#if the user chooses not to connect to the local HTTP server, don't attempt to report any information
			return None
		
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
	#print(now, datme)
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
			report_error(type, input, local_only)

def report_error(type, input, local_only=False):
	"""
	Reports an error to the local server and prints it to the console.
	Also checks if the error is in a loop and exits if it is.

	:param type: The type of error.
	:param input: The error message or data to report.
	:param local_only: If True, only report to the local server.
	"""

	try:
		global last_played_video_source
		global last_report_error
		message = ""
		
		# last reported error was less than 3 seconds ago
		if (time.time() - last_report_error[0]) < 3:
			# over 100 errors reported		
			if last_report_error[2] >= 100:
				if local_only != True:
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
		print("#ERROR: " + type + " :: " + message + addmsg)

		if local_only != True:
			contents = open_url("http://127.0.0.1/?error=" + urllib.quote_plus(ensure_string(type) + "|" + ensure_string(message)))
		
		last_report_error = [last_report_error[0], time.time(), last_report_error[2] + 1]
	except Exception as e:
		contents = open_url("http://127.0.0.1/?error=ERROR_REPORTING_ERROR")

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
	"""
	global now
	global settings
	now = now_totheminute() # set the date time
	# check if the settings file has a test date 
	if 'time_test' in settings:
		if settings['time_test'] != None:
			# if it is, we set the global 'now' var to the test date
			# useful for testing holiday programming and other date/time specific programming
			now = datetime.datetime.strptime(str(settings['time_test']), '%b %d %Y %I:%M%p')

def update_settings():
	"""	Updates the global settings variable by reading the settings file and validating its JSON format. """
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

############################ global variables
now = datetime.datetime.now() # set the date time
script_start_time = time.time()
channel_name_static = None

error_count = 0
last_video_played = ""
last_error = (7.0,'',0)

base_directory = os.path.dirname(__file__)

############################ /global variables

############################ settings
SETTINGS_VERSION = 0.993

if base_directory != "":
	base_directory = base_directory + "/" if base_directory[-1] != "/" else base_directory

settings = None
settings_file = base_directory + "settings.json"

update_settings()

############################ /settings

allow_chance = True # We need to decided if we should allow a chance of a video to play as set by our programming in the settings file
source = None
folder = None
curr_static = None
start_time = time.time()
err_extra = []
error_channel_set = False
current_video_tag = None
########################## main loop

# Alvin and the Valentine Chipmunks - I Love The Chipmunks%T(1418)%_NA_@AM@.avi

report_error("STARTUP", ["Script is now running!"])

while(1):
	try:
	
		if (time.time() - start_time) > 179:
			report_error("MAIN_LOOP", ["No Video Available to Play", "Check things like TV Schedule names misspelled or erroneously added.", "Entering Error Channel mode (if set, check settings.json), otherwise the script will end now."])
			#print(get_setting(['channels', 'error'}))
			if get_setting(['channels', 'error']):
				channel_name_static = settings['channels']['error']
				error_channel_set = True
			else:
				exit()
				
	
		last_played_video_source = None
		err_extra = []
		if os.path.getmtime(settings_file) != settings['load_time']:
			# the settings file has been modified, let's reload it
			f = open(settings_file, "r")
			# we have to verify properly formatted JSON
			settings = validate_json(f.read())
			if settings == None:
				# report and exit if not
				report_error("SETTINGS", ["settings file isn't valid JSON"])
				sleep(10)
				exit()
			f.close()
			settings['load_time'] = os.path.getmtime(settings_file)
			report_error("SETTINGS", ["Settings Updated"])

		# set a minimum of 5 commercial breaks if is not set in the settings file
		if 'commercials_per_break' not in settings: settings['commercials_per_break'] = 5

		update_current_time()

		if 'time_test' in settings:
			if settings['time_test'] != None:	
				report_error("TEST_TIME", ["Using Test Date Time " + ensure_string(now)])

		# break down the date and time for ease of use
		month = now.month
		h = now.hour
		m = now.minute
		d = now.weekday()
		ddm = now.day

		folder = None					# the main folder(s) the current video will be pulled from

		if get_current_channel() == None:
			print("Current channel: None")
		else:
			print("Current channel: " + get_current_channel())
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
			print('its static time')
		
		# sets an array if paths where video should be
		print(programming_schedule)
		folder = programming_schedule[0]

		#print('Selected Folder: ', str(programming_schedule[2]) + " - " + replace_all_special_words(folder[0]))
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
			
			video = None
			if programming_schedule[2] == "video" or programming_schedule[2] == "video-show" or programming_schedule[2] == "commercial": # just select a random video out of the path
				# settings wants the random video to be selected from multiple folders but prefers the random video come from the folders supplied rather than the combine contents of each folder
				# this can help balance the show played when supplying different shows where a show with more videos would be more randomly favored

				min_len=0
				if 'min-length' in programming_schedule[4]:
					min_len = programming_schedule[4]['min-length']
					if not is_number(min_len): # check to see if the min-length is a number
						report_error("PROGRAMMING", ["min-length is set but it is not a number", "check settings.json file", programming_schedule[4]])

				max_len=99999 # 2 days worth of seconds
				if 'max-length' in programming_schedule[4]:
					max_len = programming_schedule[4]['max-length']
					if not is_number(max_len): # check to see if the max-length is a number
						report_error("PROGRAMMING", ["max-length is set but it is not a number", "check settings.json file", programming_schedule[4]])

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
					video = get_videos_from_dir(rfolder, min_len, max_len) # get the videos from the selected folder
					print("Selecting video from: " + rfolder) # print the selected folder
				else:
					print("Selecting video from: ", folder) # print the selected folder
					video = get_videos_from_dir(folder[0], min_len, max_len) # get the videos from the first folder in the list
					for itemX in range(1,len(folder)):
						#print('Selected Folder:', replace_all_special_words(folder[0]))
						video = video + get_videos_from_dir(folder[itemX], min_len, max_len) # add the videos from the folder to the list of videos

				if len(video) == 0:
					report_error("PROGRAMMING", ["video folder contains no videos after checking min-length and max-length"])
					continue

			elif programming_schedule[2] == "balanced-video": # select a random video from a single directory balanced around play count
				url=""
				for itemX in range(0,len(folder)):
					url = url + "&f" + ensure_string(itemX+1) + "=" + urllib.quote_plus(ensure_string(folder[itemX]))
				urlcontents = open_url("http://127.0.0.1/?get_next_rnd_episode_from_dir=1" + url)
				#rfolder = random.choice(folder)
				#urlcontents = open_url("http://127.0.0.1/?get_next_rnd_episode=" + urllib.quote_plus(rfolder))
				print("BV-Response: ", urlcontents, "BV-Sent", "http://127.0.0.1/?get_next_rnd_episode_from_dir=1" + url)
				acontents = urlcontents.split("|") #split the response, the next episode will be the first result
				try:
					if(acontents[1]=="0" or "|" not in urlcontents):
						#could not find the next episode, so we should just report the error and let a random video be selected below
						video = [ random.choice(get_videos_from_dir(rfolder)) ]
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
				video = get_videos_from_dir(folder[0])
				for itemX in range(1,len(folder)):
					#print('Selected Folder:', replace_all_special_words(folder[0]))
					video = video + get_videos_from_dir(folder[itemX])

				selvideo = random.choice(video) # choose a random video from the directory to use as a reference (needs to be random in case there are multiple different directories)
				urlcontents = open_url("http://127.0.0.1/?get_next_episode=" + urllib.quote_plus(ensure_string(selvideo)))
				print("OV-Response: ", urlcontents, "OV-Sent", selvideo)
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
				# http://tv.station/?getavailable=win_Tuesday&dir=/media/pi/ssd_b/primetime/win_tuesday
				folders = None
				folders = get_folders_from_server(get_videos_from_dir(get_folders_from_dir(replace_all_special_words(folder[0]))[0])[0], replace_all_special_words(folder[0]))
				# if the server returns no folders, default to all directories in path
				if folders == None:
					folders = get_folders_from_dir(replace_all_special_words(folder[0])) # returns all subfolders of a directory
				
				selfolder = random.choice(folders) # choose a random one
				episodes_in_folder = get_videos_from_dir(selfolder) # preload all the files in that directory
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
				print("OS-Response: ", urlcontents, "OS-Sent", episodes_in_folder[0])
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
							video = episodes_in_folder
				except:
					report_error("SHOW EPISODES IN ORDER", [ urlcontents ])
			elif programming_schedule[2] == "show": # select random directory from path and then play a random video
				folders = get_folders_from_dir(replace_all_special_words(folder[0])) # returns all subfolders of a directory
				selfolder = random.choice(folders) # choose a random one
				video = get_videos_from_dir(selfolder) # preload all the files in that directory
			else:
				report_error("Programming Schedule", [ "unknown type set for video", programming_schedule[2] ])

			if video != None:
				if len(video) == 0:
					report_error("PLAY", ["video folder contains no videos", "SOURCE", ensure_string(folder[0]),"SELECTED", ensure_string(selfolder), "PROGRAMMING:", programming_schedule[0], programming_schedule[1], programming_schedule[2]])
			
			# if we managed to find some videos, we can proceed	
			if video:
				# check if minimum and maximum length for the video to be played

				# we can now select our random video from the folder(s)
				source = random.choice(video)

				# get the tag from the video file name
				current_video_tag = get_video_tag(source)

				if 'set-tag' in programming_schedule[4] and current_video_tag == None: # file name tags override the programming schedule tag
					# if the video file name does not have a tag, we use the programming schedule tag
					current_video_tag = programming_schedule[4]['set-tag']

				# load commercial break time stamps for this video (if any)
				commercials = get_commercials(source)
				
				# log it if there's no source file
				if source==None:
					report_error("PLAY", ["no source video", programming_schedule[0], programming_schedule[1], programming_schedule[2]])
					continue
				


				# check if the video is within the length range
				#source_length = get_length_from_file(source)				
				#if source_length<min_len or source_length>max_len:
				#	#report_error("MIN_MAX LENGTH", ["min:", str(min_len), "max:", str(max_len), "source", str(source_length)])
				#	continue
		
				acontents = ["default","0","values"]

				# skip this video if it has been played recently and try again
				if acontents[1]!="0":
					print("Just played this show, skipping")
					allow_chance = False # we were not successful in selecting a video (because this last one was recently played), so we won't take a chance on a different video. we'll try again on playing a non-chanced video
				else:
					allow_chance = True # now that we've found a video to play, we can allow random content again
					# if we're filling time with commercials, then we need to log them
					
					if not settings['insert_commercials']: settings['commercials_per_break'] = 1 

					pregenerated_commercials_list = None
					commercials_per_break = settings['commercials_per_break']
					tTime = get_length_from_file(source) # get video duration from file name
					if settings['commercials_per_break'] == "auto" and programming_schedule[2] != "commercial" and len(commercials)>0:
						if tTime == None: # video length was not found, so we revert to random commercials without checking for time
							commercials_per_break = 0
							report_error("COMM_BREAK", ["length of video could not be found", "SOURCE", ensure_string(source)])
						else:
							tDiff = calculate_fill_time(tTime, now, get_setting([ "commercials_offset_time" ], 0)) # calculate the time difference between the video length and the current time
							print("length:", tTime,  "diff to make up:", tDiff, "current time:", month, h, m, d, ddm)
							pregenerated_commercials_list = generate_commercials_list(tDiff, get_setting([ "commercials_fill_time_multiplier" ], 50)) # generate a list of commercials to play based on the length of the video and the current time

							commercials_per_break = 0
					elif programming_schedule[2] == "commercial":
						commercials_per_break = 0
						urlcontents = open_url("http://127.0.0.1/?current_comm=" + urllib.quote_plus(ensure_string(source)))
					else:
						if is_number(settings['commercials_per_break']) == False:
							# if commercials_per_break should be set to 'auto' or a NUMBER. If it's not a number, we assume it should be set to auto but there might not be a commercials file available for this video, so we schedule no commercials for this video.
							commercials_per_break = 0
							if len(commercials)>0:
								# if the commercials file exists, then something else went wrong and we report it
								report_error("COMM_BREAK", ["commercials per break is not a number", "If set to 'auto', make sure the video's filename contains the video's length %T(0000)%", "Check that a .commercials file exists with commercial time stamps", "FILE", ensure_string(source)])
						else:
							commercials_per_break = settings['commercials_per_break'] - 1

					if is_number(commercials_per_break) == False:
						commercials_per_break = 0
						report_error("COMM_BREAK", ["commercials per break was not a number, it should be!"])

					print('Attempting to play: ', source)
					#if 'debug' in settings:
					#	if settings['debug'] != None:
					#		report_error("PLAY INFO", [str(now), json.dumps(programming_schedule)])
					play_video(source, commercials, commercials_per_break if pregenerated_commercials_list == None else pregenerated_commercials_list, 0)
					start_time = time.time()
					if error_channel_set == True:
						error_channel_set = False
						channel_name_static = None

				print("-------------------------------------------------------------------------------")
				# send an uptime (time since last restart) tick to the web server
				report_error("UPTIME", [str(get_uptime())])
				# reboot the pi after playing a video in the early AM.
				try:
					now = datetime.datetime.now()
					if (now.hour >= 2 and now.hour <= 5 and get_uptime() > 36000):
						report_error("RESTART", ["success"])
						sleep(0.5)
						restart()
				except Exception as e:
					report_error("RESTART", ["failed"])

		else:
			print('no folder')
	except Exception as valerr:
		report_error("MAIN_LOOP", [valerr, source, folder, "-".join(err_extra),traceback.format_exc()])