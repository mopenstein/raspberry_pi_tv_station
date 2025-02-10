#!/usr/bin/python

# version: 101.97
# version date: 2025.02.10
#	Programming: 
#		All special holidays can now be checked within a +/- range in the settings.
#
#	Web-UI: 
#		The documentation on settings.php has been added to and updated to reflect the latest changes.
#		dir.php now has more precise control over video playing.
#
#	Settings:
#		The changes to speacial keyword Special Holidays effects Settings as well
#
# settings version: 0.991
#	special keyword 'easter' will now return True if it's Easter as well as a range of days from easer.
#		example:
#			"special": "easter"		// returns True if it's Easter day
#			"special": "thanksgiving"	// returns True it's Thanksgiving day
#			"special": "thanksgiving-10"	// returns True if current date is within 10 days before Thanksgiving 
#			"special": "xmas"	// returns True if it's after Thanksgiving day and Christmas day or earlier
#
# Do not expose your Raspberry Pi directly to the internet via port forwarding or DMZ.
# This software is designed for local network use only.
# Opening it up to the web will ruin your day.

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

last_played_video_source = None

def play_video(source, commercials, max_commercials_per_break, start_pos):
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

	global settings
	if source==None:
		return
	
	try:
		global last_played_video_source
		global error_count

		current_position = 0
		gend_commercials = None
		
		err_pos = -1.0
		if type(max_commercials_per_break) == list: #if a list of commercials was passed instead of a number, set variables
			gend_commercials = max_commercials_per_break
			if len(gend_commercials)!=0 and len(commercials)!=0:
				max_commercials_per_break = math.ceil(float(len(gend_commercials)) / float(len(commercials))) - 1
		print("MAXCOMM: " + str(max_commercials_per_break))
		err_pos = 0.01
		if max_commercials_per_break>0:
			#if we're inserting commercials, let's make sure we can find some
			comm_source = get_random_commercial()
			if comm_source == None: 
				#couldn't find a commercial, so we won't even try to play any during the current video but we should report the error
				max_commercials_per_break = 0 #setting max commercials to 0, overrides the passed value and disables commercials during this video
		err_pos = 0.1
		comm_player = None
		err_pos = 0.2
		
		print('Main video file:' + source)
		err_pos = 0.21
		contents = open_url("http://127.0.0.1/?current_video=" + urllib.quote_plus(source))
		err_pos = 0.23
		sleep(0.5)
		player = OMXPlayer(source, args=settings["player_settings"], dbus_name="omxplayer.player" + str(random.randint(0,999)))
		err_pos = 0.3
		sleep(0.5)
		err_pos = 0.32
		#player.set_aspect_mode('stretch');
		err_pos = 1.0
		player.pause()
		err_pos = 1.1
		player.play()
		err_pos = 1.3
		lt = 0
		
		player.seek(start_pos)
		
		while (1):
			last_played_video_source = source
			err_pos = 2.0
			try:
				position = player.position()
				current_position = position
			except:
				break
			
			try:
				#check to see if commercial times were passed and also check to see if the max commercials allowed is greater than 0
				if len(commercials) > 0 and max_commercials_per_break>0:
					#found a commercial break, play some commercials
					if float(position)>=float(commercials[0]) and position>0:
						#remove the currently selected commercial time from list of positions
						commercials.pop(0)
						#pause and hide the main video player
						err_pos = 3.0
						try:
							err_pos = 3.01
							player.hide_video()
							err_pos = 3.02
							player.pause()
						except:
							err_pos = 3.04
							print("player hide/pause error")
						
						sleep(0.5)
						#set local variable to the passed maximum amount of commercials per break allowed, so we can countdown to 0
						comm_i = max_commercials_per_break

						err_pos = 3.1
						#loop to play commercials until we've played them all
						
						
						
						while(comm_i>=0):
							try:
							
								#get a random commercial and report it to the webserver for stats
								err_pos = 3.3
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
										comm_source = gend_commercials[0]
										gend_commercials.pop(0)
										print("Commercials remaining:", len(gend_commercials))
								last_played_video_source = comm_source
								print('Playing commercial #' + str(comm_i), comm_source)
								err_pos = 3.4
								contents = open_url("http://127.0.0.1/?current_comm=" + urllib.quote_plus(comm_source))
								#load commercial
								err_pos = 3.5
								if comm_player == None:
									comm_player = OMXPlayer(comm_source, args=settings["player_settings"], dbus_name="omxplayer.player1")
									err_pos = 3.52
									comm_player.set_video_pos(0,0,670,480);
									err_pos = 3.53
									comm_player.set_aspect_mode('stretch');
								else:
									err_pos = 3.54
									comm_player.load(comm_source)
								err_pos = 3.6
								#comm_player.pause()
								
								sleep(0.5)
								err_pos = 3.7
							
								#if it's the first commercial, show the player. no need to show it after that
								if comm_i==max_commercials_per_break:
									err_pos = 3.8
									comm_player.show_video()
								
								#play commercial
								err_pos = 3.9
								comm_player.play()
								err_pos = 4.0

								#we need to wait until the commercial has completed, so we'll check for the current position of the video until it triggers an error and we can move on
								comm_length = get_length_from_file(comm_source) 	# get the length of commercial being played
								comm_start_time = time.time()						# get current time stamp
								comm_end_time = comm_start_time + comm_length + 2	# calculate at what time the commercial should have ended plus a little buffer (2 seconds)
								
								while (1):
									if time.time() > comm_end_time: # commercial should be over by now, so we move on
										break
										
									try:
										err_pos = 4.1
										comm_position = math.floor(comm_player.position())
									except:
										break
									
									#sometimes the main player doesn't hide/pause. we should make sure that it does
									try:
										if player.is_playing() == True:
											err_pos = 4.11
											player.hide_video()
											err_pos = 4.12
											player.pause()
									except:
											err_pos = 4.14
											print("player hide/pause error")
							except Exception as exce:
								err_pos = 3.92
								#if there was an error playing commercials, report it and resume playing main video
								error_count = error_count + 1
								report_error("COMM_PLAY_LOOP", ["Position", str(err_pos), "error", str(exce), "SOURCE", comm_source])
							
							#decrement the amount of remaining commercials
							comm_i = comm_i - 1
							sleep(1)
							
						err_pos = 5.0
						#show and resume the currently playing main video after the commercial break
						player.show_video()
						player.play()
						
			except Exception as ecce:
				err_pos = err_pos + .01
				#if there was an error playing commercials, report it and resume playing main video
				error_count = error_count + 1
				report_error("COMM_PLAY", ["Position", str(err_pos), "Error", str(ecce)])
				player.show_video()
				player.play()


		err_pos = 7.0
		#main video has ended
		player.hide_video()
		sleep(0.5)
	except Exception as e:
		if(err_pos!=7.0):
			error_count = error_count + 1
			report_error("PLAY_LOOP", ["Postion", str(err_pos), "Error", str(e), "SOURCE", str(source)])
		
		#kill all omxplayer instances since there was problem with the main video.
		kill_omxplayer()
		try:
			comm_player.quit()
		except Exception as ex:
			print("error comm quit " + str(ex))
		try:
			player.quit()
		except Exception as exx:
			print("error player quit " + str(exx))
			
		return (err_pos, source, current_position)

	try:
		comm_player.quit()
	except Exception as ex:
		report_error("COMM_quit", ["Position", str(err_pos), "Error", str(ex)])
	try:
		player.quit()
	except Exception as exx:
		report_error("PLAY_quit",["Position", str(err_pos), "Error", str(ex)])
	
	return

def wildcard_array():
	return ['all', 'any', '*']

def update_settings():
	# ensures that the settings file is a valid JSON, handles any errors if it's not, and verifies that the version information is correct.
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

def eval_equation(chance, now):
	# tries to take a string that should be a mathematical equation and calculate and return an answer
	# uses EVAL() but santizes by removing anything not a number, math symbol, period, or parentheses
	# replaces certain KEYWORDS to the corresponding value
	try:
		chance = chance.lower()
		replacements = {
			"maxdays": calendar.monthrange(now.year, now.month)[1],
			"weekday": now.weekday(),
			"day": now.day,
			"month": now.month,
			"hour": now.hour,
			"minute": now.minute,
			"year": now.year
		}
		for key, value in replacements.items():
			chance = chance.replace(key, str(value) + ".0")
		equation = re.sub(re.compile(r'[^\d+\-*/^=<>()\.]'), '', chance)
		return eval(equation)
	except:
		return 0

def get_short_month_name(month_number):
	month_abbr = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"]
	if 1 <= month_number <= 12:
		return month_abbr[month_number - 1]
	else:
		raise ValueError("Month number must be in the range 1-12")

# Function to replace %MAXDAYS% with the correct number of days in the month
def replace_special_words(date_str):
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

def check_video_times(obj, channel=None, allow_chance=True):
	try:
		update_current_time()
		global now
		print(now)
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
					if is_within_range(timeItem['between'])==False:
						continue

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
			
			#if useThisOne:
			return [timeItem['name'], True if skip['chance']==False else False, video_type, is_static, timeItem, now]
	except Exception as valerr:
		report_error("CHECK_TIMES", [str(valerr),traceback.format_exc()])

	return None

def generate_commercials_list(total_time_seconds):
	"""
	Fills the given total time with randomly selected choices from a random commercial.
	:param total_time_seconds: Total time in seconds.
	:return: A list of randomly selected commercials that (roughly) sum up to the total time.
	"""
	remaining_time = total_time_seconds
	selected_intervals = []
	lowest_time = 1000
	start_time = time.time()
	while remaining_time > 40:
		c = get_random_commercial()
		if c == None:
			report_error("GEN_COMM_LOST", ["could not get a random commercial"])
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

		#if cTime <= remaining_time:
		if remaining_time - cTime > 29:
			selected_intervals.append(c)
			remaining_time -= cTime
		
		if cTime < lowest_time: lowest_time = cTime

		if time.time() - start_time > 5:
			if remaining_time < lowest_time: break
		
	return selected_intervals

def get_args(index):
	if index < len(sys.argv):
		return sys.argv[index]
	return None 

def get_commercials(source):
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
		report_error("GET_VIDEO_LENGTH", [str(ValueError), str(file)])
		return None

def get_random_commercial():
	global settings

	try:
		programming_schedule = check_video_times(settings['commercial_times'], get_current_channel()) # check to see if any commercials are scheduled
		if programming_schedule == None:
			#no specified commercials were set for this time perioid, even tho the settings suggests there might be
			report_error("GET_RND_COMM", ["No commercials block found", "check settings.json file"]) #could be intentional, but we're going to report it just in case
			return None

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
			# this can help balance the show played when supplying different shows where a show with more videos would be more randomly favored
			folder_chance = None 
			if 'prefer-folder' in programming_schedule[4]:
				folder_chance = programming_schedule[4]['prefer-folder']
			
			if folder_chance != None:
				rfolder = random.choice(folder)
				video = get_videos_from_dir(replace_all_special_words(rfolder))
			else:
				video = get_videos_from_dir(replace_all_special_words(folder[0]))
				for itemX in range(1,len(folder)):
					#print('Selected Folder:', replace_all_special_words(folder[0]))
					video = video + get_videos_from_dir(replace_all_special_words(folder[itemX]))
				
			return random.choice(video) #return a random video from the combined paths
	except Exception as valerr:
		#an unknown error occurred, report it and return nothing
		
		report_error("GET_RND_COMM", ["##unexpected error##", "check settings.json file", str(valerr)])
		return None	

channel_name_static = None
last_channel_name = None;
def get_current_channel():
	global channel_name_static
	# channel names can be set in the settings file to correspond with programming
	#
	# check to see if the script has set a channel.
	if channel_name_static != None:
		return channel_name_static
	
	# check for external channel
	# a plain text file containing a 'channel' name 
	global settings
	if not get_setting({'channels', 'file'}):
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
	return calendar.day_name[d].lower()
		
def getMonth(m):
	return [None,'january','february','march','april','may','june','july','august','september','october','november','december'][m % 12 or 12]

def get_folders_from_server(showType, showDir):
	#http://tv.station/?getavailable=win_Tuesday&dir=/media/pi/ssd_b/primetime/win_tuesday
	contents = open_url("http://127.0.0.1/?getavailable=" + urllib.quote_plus(showType) + "&dir=" + urllib.quote_plus(showDir))
	if contents == '0':
		return None
	return contents.split("\n")

def get_folders_from_dir(path):
	dirs = os.walk(path).next()[1]
	for i in range(len(dirs)):
		dirs[i] = path + "/" + dirs[i]
	return dirs
	
def get_folders_by_show_type(type):
	dirs = os.walk(path).next()[1]
	for i in range(len(dirs)):
		dirs[i] = path + "/" + dirs[i]
	return dirs

def clean_up_cache_files(dir_name,dir):
	test = os.listdir(dir_name)

	for item in test:
		if item.endswith(".cache"):
			if item.split()[0] == dir:
				os.remove(os.path.join(dir_name, item))

def get_videos_from_dir(dir):
		# loads all video files in a directory into an array
		# cached name is dir-nonalnum  + " " + last mod time + " " + .cache
		cache_dir_name = os.path.join(os.path.dirname(os.path.realpath(__file__)), "cache")
		cache_fname = os.path.join(cache_dir_name, re.sub(r'[^a-zA-Z0-9]', '', dir) + " " + str(os.path.getmtime(dir)) + " .cache")
		if os.path.isfile(cache_fname) == False:
			results = glob.glob(os.path.join(dir, '*.mp4'))
			results.extend(glob.glob(os.path.join(dir, '*.avi')))
			results.extend(glob.glob(os.path.join(dir, '*.webm')))
			results.extend(glob.glob(os.path.join(dir, '*.mpeg')))
			results.extend(glob.glob(os.path.join(dir, '*.m4v')))
			results.extend(glob.glob(os.path.join(dir, '*.mkv')))
			results.extend(glob.glob(os.path.join(dir, '*.mov')))
			results.extend(glob.glob(os.path.join(dir, '*.flv')))
			results.extend(glob.glob(os.path.join(dir, '*.wmv')))

			clean_up_cache_files(cache_dir_name, re.sub(r'[^a-zA-Z0-9]', '', dir))
			
			with open(cache_fname, 'w') as f:
				json.dump(results, f)
		else:
			with open(cache_fname, 'r') as f:
				results = json.load(f)
		return results


def get_uptime():
	global script_start_time
	return (time.time() - script_start_time)

def is_date_within_range(date1, date2, range_days):
	delta = (date1 - date2).days
	if (range_days >= 0 and 0 <= delta <= range_days) or (range_days < 0 and range_days <= delta <= 0):
		return True
	return False

def IsEaster(daysFromEaster):
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
	target_date = easter_sunday + datetime.timedelta(days=daysFromEaster)
	return is_date_within_range(datetime.date(now.year, now.month, now.day), easter_sunday, daysFromEaster)

def is_number(s):
	try:
		float(s)
		return True
	except ValueError:
		return False

def is_special_time(check):
	if check[:4].lower() == 'xmas':
		if is_number(check[4:].strip()):
			num = int(check[4:])
		else:
			if IsThanksgiving(8) and IsXmas(-25):
				print(True)
			else:
				print(False)

	if check[:12].lower() == 'thanksgiving':
			num = 0
			if is_number(check[12:].strip()):
					num = int(check[12:])

	if check[:6].lower() == 'easter':
		num = 0
		if is_number(check[6:].strip()):
			num = int(check[6:])
		return True if IsEaster(num) else False
	return False

def kill_omxplayer():
	command = "/usr/bin/sudo pkill omxplayer"
	process = subprocess.Popen(command.split(), stdout=subprocess.PIPE)
	output = process.communicate()[0]
	print(output)

def now_totheminute():
	jt_string = str(datetime.datetime.now().day).zfill(2) + "/" + str(datetime.datetime.now().month).zfill(2) + "/" + str(datetime.datetime.now().year).zfill(4) + " " + str(datetime.datetime.now().hour).zfill(2) + ":" + str(datetime.datetime.now().minute).zfill(2)
	return datetime.datetime.strptime(jt_string, "%d/%m/%Y %H:%M")

def open_url(url):
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
	global now # always use the global datetime 'now' so it doesn't break test dates
	
	xmas = datetime.datetime.strptime(str("Dec 25 " + str(now.year) + " " + str(now.hour).zfill(2) + ":" + str(now.minute).zfill(2)), '%b %d %Y %H:%M')
	target_date = xmas + datetime.timedelta(days=daysFromXmas)
	return is_date_within_range(now, xmas, daysFromXmas)

def IsThanksgiving(daysFromThanksgiving):
	global now # always use the global datetime 'now' so it doesn't break test dates
	
	year = str(now.year)
	d = datetime.datetime.strptime(str("Nov 1 " + year), '%b %d %Y')
	dw = d.weekday()
	thanksgiving = datetime.datetime.strptime(str("Nov " + str(22 + (10 - dw) % 7) + " " + str(d.year) + " " + str(now.hour).zfill(2) + ":" + str(now.minute).zfill(2)), '%b %d %Y %H:%M')
	target_date = thanksgiving + datetime.timedelta(days=daysFromThanksgiving)
	return is_date_within_range(now, thanksgiving, daysFromThanksgiving)

def PastThanksgiving(is_thanksgiving):
	global now # always use the global datetime 'now' so it doesn't break test dates

	
	year = str(now.year)
	d = datetime.datetime.strptime(str("Nov 1 " + year), '%b %d %Y')
	dw = d.weekday()
	datme = datetime.datetime.strptime(str("Nov " + str(22 + (10 - dw) % 7) + " " + str(d.year) + " " + str(now.hour).zfill(2) + ":" + str(now.minute).zfill(2)), '%b %d %Y %H:%M')
	#print(now, datme)
	if(is_thanksgiving==True): #check if today is thanksgiving
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

#   			    [ first reported time, last reported time, count ]
last_report_error = [0, 0, 0]

def report_error(type, input, local_only=False):

	# some times the script will get stuck in an error loop (maybe the hard drive failed for example)
	# we should probably exit gracefully if that happens instead of looping forever.

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
			message = message + '|'.join(str(x) for x in s) + "|"
		elif isinstance(s, basestring)==True:
			message = message + s + "|"
		else:
			message = message + "REPORT_ERROR|unknown input type|"
			try:
				message = message + str(s) + "|"
			except Exception as e:
				message = message + str(e) + "|"
			
	if message[-1]=="|":
		message = message[:-1]

	addmsg=""
	if last_played_video_source!=None:
		addmsg = "|Source|" + last_played_video_source
	print("#ERROR: " + type + " :: " + message + addmsg)

	if local_only != True:
		contents = open_url("http://127.0.0.1/?error=" + urllib.quote_plus(returncleanASCII(str(type)) + "|" + returncleanASCII(str(message))))
	
	last_report_error = [last_report_error[0], time.time(), last_report_error[2] + 1]

def restart():
	command = "/usr/bin/sudo /sbin/shutdown -r now"
	process = subprocess.Popen(command.split(), stdout=subprocess.PIPE)
	output = process.communicate()[0]
	print output

def returncleanASCII(s):
		sb = ""
		for i in range(0, len(s)):
				if (ord(s[i:i+1]) >= 32 and ord(s[i:i+1]) <= 127 or s[i:i+1] == '%'):
						sb = sb + s[i:i+1]
		return sb

def validate_json(json_str):
	try:
		return json.loads(json_str, cls=ReferenceDecoder)
	except ValueError as err:
		return None

def get_setting(find):
	try:
		global settings
		temp = settings
		for k in find:
			if k in temp:
				temp = temp[k]
			else:
				return None
		return temp
	except:
		return None

def update_current_time():
	global now
	global settings
	now = now_totheminute() # set the date time
	# check if the settings file has a test date 
	if 'time_test' in settings:
		if settings['time_test'] != None:
			err_count = 2.0
			# if it is, we set the global 'now' var to the test date
			# useful for testing holiday programming and other date/time specific programming
			now = datetime.datetime.strptime(str(settings['time_test']), '%b %d %Y %I:%M%p')

############################ global variables
now = datetime.datetime.now() # set the date time
script_start_time = time.time()
channel_name_static = None

error_count = 0
last_video_played = ""
last_error = (7.0,'',0)

TEST_FILE = False if os.path.basename(__file__)[0:5] != "test_" else True
base_directory = os.path.dirname(__file__)

############################ /global variables

############################ settings
SETTINGS_VERSION = 0.991

if base_directory != "":
	base_directory = base_directory + "/" if base_directory[-1] != "/" else base_directory

settings = None
settings_file = base_directory + "settings.json" if TEST_FILE == False else base_directory + "test_settings.json"

update_settings()

############################ /settings

allow_chance = True # We need to decided if we should allow a chance of a video to play as set by our programming in the settings file
source = None
folder = None
err_count = 0.0
curr_static = None
start_time = time.time()
err_extra = []
error_channel_set = False
########################## main loop

report_error("STARTUP", ["Script is now running!"])

while(1):
	try:
	
		if (time.time() - start_time) > 179:
			report_error("MAIN_LOOP", ["No Video Available to Play", "Check things like TV Schedule names misspelled or erroneously added.", "Entering Error Channel mode (if set, check settings.json), otherwise the script will end now."])
			#print(get_setting({'channels', 'error'}))
			if get_setting({'channels', 'error'}):
				channel_name_static = settings['channels']['error']
				error_channel_set = True
			else:
				exit()
				
	
		last_played_video_source = None
		err_extra = []
		if os.path.getmtime(settings_file) != settings['load_time']:
			# the settings file has been modified, let's reload it
			err_count = 1.0
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
				report_error("TEST_TIME", ["Using Test Date Time " + str(now)])

		err_count = 3.0
		# break down the date and time for ease of use
		month = now.month
		h = now.hour
		m = now.minute
		d = now.weekday()
		ddm = now.day

		folder = None					# the main folder(s) the current video will be pulled from

		err_count = 3.1
		if get_current_channel() == None:
			print("Current channel: None")
		else:
			print("Current channel: " + get_current_channel())
		# check if a static programming block has been set
		if curr_static == None: # there is no static block
			err_count = 3.11
			# checks the current date/time against the programming schedule in the settings file
			
			update_settings()
			programming_schedule = check_video_times(settings['times'], get_current_channel(), allow_chance)
			if programming_schedule == None:
				report_error("PROGRAMMING_SCHEDULE", [ "The programming schedule returned no results.", "Check your settings file to ensure programming is set for this time.", "Entering Error Channel mode (if set, check settings.json), otherwise the script will end now." ])
				if get_setting({'channels', 'error'}):
					channel_name_static = settings['channels']['error']
					error_channel_set = True
					continue
				else:
					exit()
			
			err_count = 3.12
			if programming_schedule[3] != None:
				if programming_schedule[3][0] == True:
					# the schedule has returned a static choice.
					# this blocks off a certain amount of time to retain the chosen schedule
					err_count = 3.13
					programming_schedule[3][1] = time.mktime(now.timetuple()) + float(programming_schedule[3][1]) # set the cut off time from NOW + the time designated by settings
					err_count = 3.14
					curr_static = programming_schedule # use the current programming block for future refence until NOW > time set by settings
					channel_name_static = programming_schedule[3][2]
					report_error("STATIC", ["static set", programming_schedule[3][2]])
		else: # there is a static block
			#check if the static block allotted time has passed
			err_count = 3.15
			#print(time.mktime(now.timetuple()))
			#print(curr_static[3][1])
			if time.mktime(now.timetuple()) > curr_static[3][1]:
				err_count = 3.16
				curr_static = None
				channel_name_static = None
		
			# set current programming block to the static one
			# if the static channel has ended, programming schedule will be empty and the loop will skip it
			err_count = 3.17
			programming_schedule = curr_static
			print('its static time')
		
		# sets an array if paths where video should be
		print(programming_schedule)
		err_count = 3.19
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
			err_count = 3.2
			if programming_schedule[2] == "video" or programming_schedule[2] == "video-show" or programming_schedule[2] == "commercial": # just select a random video out of the path
				err_count = 3.3
				
				# settings wants the random video to be selected from multiple folders but prefers the random video come from the folders supplied rather than the combine contents of each folder
				# this can help balance the show played when supplying different shows where a show with more videos would be more randomly favored
				folder_chance = None 
				if 'prefer-folder' in programming_schedule[4]:
					folder_chance = programming_schedule[4]['prefer-folder']
				
				if folder_chance != None:
					rfolder = random.choice(folder)
					video = get_videos_from_dir(rfolder)
					print("Selecting video from: " + rfolder)
				else:
					print("Selecting video from: ", folder)
					video = get_videos_from_dir(folder[0])
					for itemX in range(1,len(folder)):
						#print('Selected Folder:', replace_all_special_words(folder[0]))
						video = video + get_videos_from_dir(folder[itemX])
			elif programming_schedule[2] == "balanced-video": # select a random video from a single directory balanced around play count
				url=""
				for itemX in range(0,len(folder)):
					url = url + "&f" + str(itemX+1) + "=" + urllib.quote_plus(folder[itemX])
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
				err_count = 3.35
				video = get_videos_from_dir(folder[0])
				for itemX in range(1,len(folder)):
					#print('Selected Folder:', replace_all_special_words(folder[0]))
					video = video + get_videos_from_dir(folder[itemX])

				selvideo = random.choice(video) # choose a random video from the directory to use as a reference (needs to be random in case there are multiple different directories)
				urlcontents = open_url("http://127.0.0.1/?get_next_episode=" + urllib.quote_plus(selvideo))
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
				err_count = 3.4
				# get a list of folders from the local server
				# http://tv.station/?getavailable=win_Tuesday&dir=/media/pi/ssd_b/primetime/win_tuesday
				folders = None
				folders = get_folders_from_server(get_videos_from_dir(get_folders_from_dir(replace_all_special_words(folder[0]))[0])[0], replace_all_special_words(folder[0]));
				# if the server returns no folders, default to all directories in path
				if folders == None:
					folders = get_folders_from_dir(replace_all_special_words(folder[0])) # returns all subfolders of a directory
				
				selfolder = random.choice(folders) # choose a random one
				episodes_in_folder = get_videos_from_dir(selfolder) # preload all the files in that directory
				if len(episodes_in_folder) <= 0:
					report_error("FOLDERS", [ "Issue selecting video from folder.", "Is the folder empty?", selfolder, "Entering Error Channel mode (if set, check settings.json), otherwise the script will end now." ])
					if get_setting({'channels', 'error'}):
						channel_name_static = settings['channels']['error']
						error_channel_set = True
						continue
					else:
						exit()
				# to get the next video from the PHP front end we need to pass one of the episode's file names so it can determine the "short name"
				urlcontents = open_url("http://127.0.0.1/?get_next_episode=" + urllib.quote_plus(episodes_in_folder[0]))
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
				err_count = 3.45
				folders = get_folders_from_dir(replace_all_special_words(folder[0])) # returns all subfolders of a directory
				selfolder = random.choice(folders) # choose a random one
				video = get_videos_from_dir(selfolder) # preload all the files in that directory
			else:
				report_error("Programming Schedule", [ "unknown type set for video", programming_schedule[2] ])

			if video != None:
				if len(video) == 0:
					err_count = 3.5
					report_error("PLAY", ["video folder contains no videos", "SOURCE", str(folder[0]),"SELECTED", str(selfolder), "PROGRAMMING:", programming_schedule[0], programming_schedule[1], programming_schedule[2]])
			
			# if we managed to find some videos, we can proceed	
			if video:
				err_count = 3.6
				# we can now select our random video from the folder(s)
				source = random.choice(video)
				#print('Actual file: ', source)
				# load commercial break time stamps for this video (if any)
				commercials = get_commercials(source)
				
				# log it if there's no source file
				if source==None:
					err_count = 4.0
					report_error("PLAY", ["no source video", programming_schedule[0], programming_schedule[1], programming_schedule[2]])
					continue
				
				min_len=-1
				if 'min-length' in programming_schedule[4]:
					min_len = programming_schedule[4]['min-length']
				max_len=172800 # 2 days worth of seconds
				if 'max-length' in programming_schedule[4]:
					max_len = programming_schedule[4]['max-length']
		
				source_length = get_length_from_file(source)
				
				if source_length<min_len or source_length>max_len:
					#report_error("MIN_MAX LENGTH", ["min:", str(min_len), "max:", str(max_len), "source", str(source_length)])
					continue
		
				acontents = ["default","0","values"]
				
				#if this is the test version of the script, don't report videos to server
				if TEST_FILE == False or get_args(1)=="skip_test":
					err_count = 5.0
					# don't check if a video has been recently played if it is a user set programming chance (otherwise it will be compared to the rest of the videos and could be skipped if it was played too recently)
					if not programming_schedule[1] and settings['report_data']:
						err_count = 5.1
						#check if this video has been played recently	
						try:
							if programming_schedule[2] == "commercial": # skip the check if the video file is a commerical
								err_count = 5.3
								print("commercial",programming_schedule)
								urlcontents = open_url("http://127.0.0.1/?current_comm=" + urllib.quote_plus(source))
								acontents = ["Commercial","0","so we skip this check and just play it"]
							elif programming_schedule[2] == "video": # skip check if the settings file designates this as a video that is randomly chosen.
								acontents = ["Randomly selected video","0","so we skip this check and just play it"]
							else: # only check if it's a proper show
								err_count = 5.2
								print("show/video",programming_schedule)
								min_time = 0
								if 'minimum time between repeats' in programming_schedule[4]:
									min_time = programming_schedule[4]['minimum time between repeats']
									print("minium time is set to " + str(min_time))
									if str(min_time).isdigit() != True:
										min_time = 0
								urlcontents = open_url("http://127.0.0.1/?getshowname=" + urllib.quote_plus(source) + "&min_time=" + str(min_time))
								print("Response: ", urlcontents)
								acontents = urlcontents.split("|")
								last_video_played = acontents[0]
								print("Last video played: " + last_video_played)
							
						except Exception as exx:
							report_error("SERVER", ["err_count", str(err_count), "server error", str(exx), "source", source])
					else:
						err_count = 5.4
						acontents = ["Took a chance on a video and it was triggered","0","so we skip this check and just play it"]

				err_count = 6.0
				# skip this video if it has been played recently and try again
				if acontents[1]!="0":
					err_count = 6.1
					print("Just played this show, skipping")
					allow_chance = False # we were not successful in selecting a video (because this last one was recently played), so we won't take a chance on a different video. we'll try again on playing a non-chanced video
				else:
					err_count = 6.2
					allow_chance = True # now that we've found a video to play, we can allow random content again
					# if we're filling time with commercials, then we need to log them
					
					if not settings['insert_commercials']: settings['commercials_per_break'] = 1 

					pregenerated_commercials_list = None
					commercials_per_break = settings['commercials_per_break']
					tTime = get_length_from_file(source) # get video duration from file name
					if settings['commercials_per_break'] == "auto" and programming_schedule[2] != "commercial" and len(commercials)>0:
						if tTime == None: # video length was not found, so we revert to random commercials without checking for time
							err_count = 6.5
							commercials_per_break = 0
							report_error("COMM_BREAK", ["length of video could not be found", "SOURCE", str(source)])
						else:
							otTime = tTime
							err_count = 6.3
							#generate a list of commercials that will fill time to the nearest half/top of the hour
							curr_minute = m
							if curr_minute>30:
								err_count = 6.4
								curr_minute = curr_minute - 30

							curr_minute=min(29, curr_minute)
							curr_minute=max(0, curr_minute)

							tTime = (tTime + (curr_minute * 60)) + 30

							err_count = 6.6
							lenCountDown = 18000 # set max length of a video (5 hours) and then count down in 30 minute increments
							if(str(commercials[0])[:7] == "length:"):
								lenCountDown = int(commercials[0][7:])
								del commercials[0]
							while(lenCountDown>1800):
								#1800 secs (30 mins) is the minimum amount of time to fill
								if tTime > (lenCountDown - 1800):
									break
								#substract 1800 seconds (30 min increments), until we have 30 mins remaining
								lenCountDown = lenCountDown - 1800
							err_count = 6.7
							tDiff = lenCountDown - tTime
							err_count = 6.8
							err_extra = [str(tTime), str(tDiff)]

							print("length:", tTime, "diff to make up:", tDiff, "orig length", otTime, "current time:", month, h, m, d, ddm)
							pregenerated_commercials_list = generate_commercials_list(tDiff)
							prect = 0
							for prec in pregenerated_commercials_list:
								prect = prect + get_length_from_file(prec)
							
							err_count = 6.9
							err_extra = []
							print("Pregenerated Commercials:", pregenerated_commercials_list, "total length:", prect)
							commercials_per_break = 0
					elif programming_schedule[2] == "commercial":
						err_count = 7.0
						commercials_per_break = 0
						urlcontents = open_url("http://127.0.0.1/?current_comm=" + urllib.quote_plus(source))
					else:
						err_count = 8.0
						if is_number(settings['commercials_per_break']) == False:
							err_count = 8.1
							# if commercials_per_break should be set to 'auto' or a NUMBER. If it's not a number, we assume it should be set to auto but there might not be a commercials file available for this video, so we schedule no commercials for this video.
							commercials_per_break = 0
							if len(commercials)>0:
								err_count = 8.2
								# if the commercials file exists, then something else went wrong and we report it
								report_error("COMM_BREAK", ["commercials per break is not a number", "If set to 'auto', make sure the video's filename contains the video's length %T(0000)%", "Check that a .commercials file exists with commercial time stamps", "FILE", str(source)])
						else:
							err_count = 8.3
							commercials_per_break = settings['commercials_per_break'] - 1

					if is_number(commercials_per_break) == False:
						err_count = 9.0
						commercials_per_break = 0
						report_error("COMM_BREAK", ["commercials per break was not a number, it should be!"])

					if TEST_FILE == True:
						err_count = 10.0
						print(source, commercials, commercials_per_break if pregenerated_commercials_list == None else pregenerated_commercials_list, 0, 'chance', programming_schedule[1])
						sleep(3)
					else:
						err_count = 11.0
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
					err_count = 12.0
					now = datetime.datetime.now()
					if (now.hour >= 2 and now.hour <= 5 and get_uptime() > 36000):
						err_count = 12.1
						report_error("RESTART", ["success"])
						sleep(0.5)
						restart()
				except Exception as e:
					report_error("RESTART", ["failed"])

		else:
			print('no folder')
	except Exception as valerr:
		report_error("MAIN_LOOP", [valerr, source, folder, err_count, "-".join(err_extra),traceback.format_exc()])


# Commercials format time in seconds to interrupt. Each commercial break is on a new line
#
#	60.0
#	600.545
#	1200.22