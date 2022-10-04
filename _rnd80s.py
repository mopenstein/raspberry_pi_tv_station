#!/usr/bin/python

# version: 101.3
# version date: 2022.07.30
#	Settings has supported multiple disk drive locations for some time. It has been implemented fully.
#	Settings updated to support static triggers for scheduling.
#		setting the static flag will return that programing choice until the time ending has been passed. useful for having a random programming block be locked in for a set time.
#	Better support for channels has been implemented.
#		setting the static flag above can also return a channel name
# settings version: 0.91
#	web-ui front ends should use the settings.json file taking care not to clash with the settings used by the python player
#	better error handling when things go wrong

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
	# If commercials are set, 2 instances are loaded: one for the main video and the other for commercials (the main player is hidden during commercial breaks and then made visible again)
	# 	source: video file to be played
	# 	commercials: array of times in seconds at which the source video will be interrupt to play commercials
	#	max_commercials_per_break:
	#		If set to a number, a random commercial will be continually selected up until the supplied number has been reached
	#		if an array of commercials video file locations is provided, each commercial will be played until there are not left
	#	start_pos: attempts to resume the video from this value
	
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
		err_pos = 1.2
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
					if float(position)>=float(commercials[0]) and position>5:
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
								while (1):
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

def check_video_times(obj, channel=None, allow_chance=True):
	try:
		global now

		month = now.month
		h = now.hour
		m = now.minute
		d = now.weekday()
		ddm = now.day
		
		dayOfWeek = getDayOfWeek(d)

		skip = dict (
			month = None,
			date = None,
			dayOfWeek = None,
			time = None,
			special = None,
			chance = None,
			channel = None
		)

		for timeItem in reversed(obj):
			is_static = [False, 0, ""]
			skip = dict (
				month = None,
				date = None,
				dayOfWeek = None,
				time = None,
				special = None,
				chance = None,
				channel = None
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
					skip['channel'] = False if timeItem['channel'] == channel else True

			if 'month' in timeItem:
				if timeItem['month'] != None:
					test = False
					for itemX in timeItem['month']:
						if itemX == month or itemX in wildcard_array(): # if it's the proper month, we proceed
							test = True
							break
					skip['month'] = False if test == True else True

			if 'date' in timeItem:
				if timeItem['date'] != None:
					test = False
					for itemX in timeItem['date']:
						if itemX == ddm or itemX in wildcard_array(): # if it's on the proper day, we proceed
							test = True
							break
					skip['date'] = False if test == True else True

			if 'dayOfWeek' in timeItem:
				if timeItem['dayOfWeek'] != None:
					test = False
					for itemX in timeItem['dayOfWeek']:
						if itemX.lower() == dayOfWeek or itemX in wildcard_array(): # if it's the proper day of the week, we proceed
							test = True
							break
					skip['dayOfWeek'] = False if test == True else True
			
			if 'start' in timeItem and 'end' in timeItem:
				if ((h >= timeItem['start'][0] or timeItem['start'][0] in wildcard_array()) and (m>=timeItem['start'][1] or timeItem['start'][1] in wildcard_array())) and ((h <= timeItem['end'][0] or timeItem['end'][0] in wildcard_array()) and (m <= timeItem['end'][1] or timeItem['end'][1] in wildcard_array())):
					skip['time'] = False
				else:
					skip['time'] = True
		
			if 'special' in timeItem:
				if timeItem['special'] != None:
					skip['special'] = False if is_special_time(timeItem['special']) else True

			if 'static' in timeItem: # this flag establishes that this schedule should stay triggered until the set time runs out
				if timeItem['static'] != None:
					if is_number(timeItem['static'][1]) == True:
						is_static = timeItem['static']
		
			if 'chance' in timeItem:
				chance_eval = timeItem['chance'] # store chance string
				if type(chance_eval) != float:
					for word in [["day", ddm], ["month", month], ["hour", h], ["minute", m], ["weekday", d]]: # replace special keywords in the chance string
						chance_eval = chance_eval.replace(word[0],str(word[1])+".0")
					chance_eval = eval(chance_eval) # evaluate the math
				skip['chance'] = False if random.random() <= float(chance_eval) and allow_chance == True else True
		
			useThisOne = True
			for itemX in skip:
				if skip[itemX] == True:
					useThisOne = False
					break
			
			if useThisOne:
				return [timeItem['name'], True if skip['chance']==False else False, video_type, is_static]
	except Exception as valerr:
		report_error("CHECK_TIMES", [str(valerr)])

	return None

def generate_commercials_list(max_time_to_fill):
	# attempts to generate a list of commercials of varying length to fill up remaining time (max_time_to_fill)
	# commercial AND shows/movies filenames must contain the length of the video in this format:
	#
	#		video_file_name_%T(length)%.mp4
	#
	# where "length" is a whole number representing the actual length of the video in seconds
	# it can appear anywhere in the filename
	# see: add_duration_to_video.py

	start_time = time.time()
	ret = []
	while(True):
		if time.time() - start_time > 5:
			report_error("GEN_COMM_LIST", ["took more that 5 seconds to generate comercials list"])
			break
		c = get_random_commercial()
		cTime = get_length_from_file(c)
		if cTime==None:
			report_error("GEN_COMM_LIST", ["commercial file has no length", str(c)])		
		while(cTime == None):
			c = get_random_commercial()
			cTime = get_length_from_file(c)
			if cTime==None:
				report_error("GEN_COMM_LIST", ["commercial file has no length", str(c)])		
		
		if max_time_to_fill - cTime > 40:
			max_time_to_fill = max_time_to_fill - cTime
			ret.append(c)
		elif max_time_to_fill - cTime > 28:
			max_time_to_fill = max_time_to_fill - cTime
			ret.append(c)
			break
		
	return ret if len(ret)>0 else None

def get_args(index):
	if index < len(sys.argv):
		return sys.argv[index]
	return None 

def get_commercials(source):
	#commercials file name must match exactly the source video with the file extension '.commercial'
	#each line of a .commercials file will be the time of a commercial break in seconds (and milliseconds if required) ex: 620.34

	#load single file for all commercials
	#usefull when commercial breaks are easily identifiable for a lot of episodes from a single show
	#first 8 chrs need to exactly the same
	if os.path.isfile(source[:8] + '.commercials.master') == True:
		with open(source[:8] + '.commercials.master') as temp_file:
			return [float(line.rstrip()) for line in temp_file]
	
	#load individual file for video
	if os.path.isfile(source + '.commercials') == True:
		with open(source + '.commercials') as temp_file:
			return [float(line.rstrip()) for line in temp_file]
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
			report_error("GET_RND_COMM", ["check settings.json file"]) #could be intentional, but we're going to report it just in case
			return None

		folder = programming_schedule[0] # the results, if any, returns None type of not
		if folder:
			#commercials are scheduled, so let's gather the videos from the specified paths (could only be 1 or could be many)
			#video = get_videos_from_dir(settings['drive'] + replace_all_special_words(folder[0]))
			video = get_videos_from_dir(replace_all_special_words(folder[0]))
			for itemX in range(1,len(folder)):
				video = video + get_videos_from_dir(replace_all_special_words(folder[itemX]))
				
			return random.choice(video) #return a random video from the combined paths
	except Exception as valerr:
		#an unknown error occurred, report it and return nothing
		
		report_error("GET_RND_COMM", ["##unexpected error## check settings.json file", str(valerr)])
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
	return ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'][d]
		
def getMonth(m):
	return [None,'january','february','march','april','may','june','july','august','september','october','november','december'][m]

def get_folders_from_dir(path):
	dirs = os.walk(path).next()[1]
	for i in range(len(dirs)):
		dirs[i] = path + "/" + dirs[i]
	return dirs
	

def get_videos_from_dir(dir):
	# loads all video files in a directory into an array
	results = glob.glob(os.path.join(dir, '*.mp4'))
	results.extend(glob.glob(os.path.join(dir, '*.avi')))
	results.extend(glob.glob(os.path.join(dir, '*.webm')))
	results.extend(glob.glob(os.path.join(dir, '*.mpeg')))
	results.extend(glob.glob(os.path.join(dir, '*.m4v')))
	results.extend(glob.glob(os.path.join(dir, '*.mkv')))
	results.extend(glob.glob(os.path.join(dir, '*.mov')))
	results.extend(glob.glob(os.path.join(dir, '*.flv')))
	results.extend(glob.glob(os.path.join(dir, '*.wmv')))
	
	return results

def get_uptime():
	global script_start_time
	return (time.time() - script_start_time)

def IsEaster():
	# Created by Martin Diers 
	# https://code.activestate.com/recipes/576517-calculate-easter-western-given-a-year/
	# Licensed under the MIT License https://mit-license.org/
	global now # always use the global datetime 'now' so it doesn't break test dates
	a = now.year % 19
	b = now.year // 100
	c = now.year % 100
	d = (19 * a + b - b // 4 - ((b - (b + 8) // 25 + 1) // 3) + 15) % 30
	e = (32 + 2 * (b % 4) + 2 * (c // 4) - d - (c % 4)) % 7
	f = d + e - 7 * ((a + 11 * d + 22 * e) // 451) + 114
	month = f // 31
	day = f % 31 + 1
	
	if(now.date()== datetime.date(now.year, month, day)):
		return True
	else:
		return False

def is_number(s):
    try:
        float(s)
        return True
    except ValueError:
        return False

def is_special_time(check):
	if check.lower() == 'xmas': return True if PastThanksgiving(False) else False
	if check.lower() == 'thanksgiving': return True if PastThanksgiving(True) else False
	if check.lower() == 'easter': return True if IsEaster() else False
	return False

def kill_omxplayer():
	command = "/usr/bin/sudo pkill omxplayer"
	process = subprocess.Popen(command.split(), stdout=subprocess.PIPE)
	output = process.communicate()[0]
	print output

def open_url(url):
	try:
		global settings
		
		if settings["report_data"] == False:
			#if the user chooses not to connect to the local HTTP server, don't attempt to report any information
			return None
		
		return urllib2.urlopen(url).read()
	except:
		return None

def PastThanksgiving(is_thanksgiving):
	global now # always use the global datetime 'now' so it doesn't break test dates
	monthdays = calendar.monthrange(now.year, 11)[1]
	datme = datetime.date(now.year, 11, monthdays)

	daysoff = 4 - datme.isoweekday()
	if daysoff > 0: daysoff -= 7
	datme += datetime.timedelta(daysoff)

	if(is_thanksgiving==True): #check if today is thanksgiving
		if now.date()==datme:
			return True
	else:
		if now.date() > datme:
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
	for r in [["%day%", str(d)], ["%day_of_week%", getDayOfWeek(d)], ["%month%", getMonth(month)]]:
		s = s.replace(*r)
	return s
				#   [ first reported time, last reported time, count ]
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
        return json.loads(json_str)
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

############################ global variables
now = datetime.datetime.now() # set the date time
script_start_time = time.time()

error_count = 0
last_video_played = ""
last_error = (7.0,'',0)

TEST_FILE = False if os.path.basename(__file__)[0:5] != "test_" else True
base_directory = os.path.dirname(__file__)

############################ /global variables

############################ settings
SETTINGS_VERSION = 0.91

if base_directory != "":
	base_directory = base_directory + "/" if base_directory[-1] != "/" else base_directory

settings_file= base_directory + "settings.json" if TEST_FILE == False else base_directory + "test_settings.json"

f = open(settings_file, "r")
# we need to verify that the settings file is JSON formatted.
settings = validate_json(f.read())
if settings == None:
	# report the error and exit script if it is not
	report_error("SETTINGS", ["settings file isn't valid JSON"])
	sleep(10)
	exit()

f.close()

if 'version' in settings:
	if is_number(settings['version']):
		if float(settings['version']) != SETTINGS_VERSION:
			report_error("Settings", ["Settings version mismatch. Things might not work so well."])
	else:
		report_error("Settings", ["Settings version mismatch. Things might not work so well."])
else:
	report_error("Settings", ["Settings version mismatch. Things might not work so well."])

settings['load_time'] = os.path.getmtime(settings_file)

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

while(1):
	try:
		if (time.time() - start_time) > 119:
			report_error("MAIN_LOOP", ["No Video Available to Play", "Check things like TV Schedule names misspelled or erroneously added.", "Entering Error Channel mode (if set, check settings.json), otherwise the script will end now."])
			print(get_setting({'channels', 'error'}))
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

		now = datetime.datetime.now() # set the date time
		# check if the settings file has a test date 
		if 'time_test' in settings:
			if settings['time_test'] != None:
				err_count = 2.0
				# if it is, we set the global 'now' var to the test date
				# useful for testing holiday programming and other date/time specific programming
				now = datetime.datetime.strptime(str(settings['time_test']), '%b %d %Y %I:%M%p')
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
			programming_schedule = check_video_times(settings['times'], get_current_channel(), allow_chance)
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
			print(time.mktime(now.timetuple()))
			print(curr_static[3][1])
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

		print('Selected Folder: ', str(programming_schedule[2]) + " - " + replace_all_special_words(folder[0]))
		if folder != None: # folders have been set, load and play video
			video = None
			err_count = 3.2
			if programming_schedule[2] == "video" or programming_schedule[2] == "commercial": # just select a random video out of the path
				err_count = 3.3
				video = get_videos_from_dir(replace_all_special_words(folder[0]))
				for itemX in range(1,len(folder)):
					#print('Selected Folder:', replace_all_special_words(folder[0]))
					video = video + get_videos_from_dir(replace_all_special_words(folder[itemX]))
			elif programming_schedule[2] == "show": # select random directory from path (this is to cut down on the time it takes to select a 'show' that hasn't been played yet)
				err_count = 3.4
				folders = get_folders_from_dir(replace_all_special_words(folder[0]))
				selfolder = random.choice(folders)
				video = get_videos_from_dir(selfolder)

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
		
				acontents = ["default","0","values"]
				
				#if this is the test version of the script, don't report videos to server
				if TEST_FILE == False or get_args(1)=="skip_test":
					err_count = 5.0
					# don't check if a video has been recently played if it is a user set programming chance (otherwise it will be compared to the rest of the videos and could be skipped if it was played too recently)
					if not programming_schedule[1] and settings['report_data']:
						err_count = 5.1
						#check if this video has been played recently	
						try:
							if programming_schedule[2] == "show" or programming_schedule[2] == "video":
								err_count = 5.2
								print("show/video",programming_schedule)
								urlcontents = open_url("http://127.0.0.1/?getshowname=" + urllib.quote_plus(source))
								print("Response: ", urlcontents)
								acontents = urlcontents.split("|")
								last_video_played = acontents[0]
								print("Last video played: " + last_video_played)
							elif programming_schedule[2] == "commercial":
								err_count = 5.3
								print("commercial",programming_schedule)
								urlcontents = open_url("http://127.0.0.1/?current_comm=" + urllib.quote_plus(source))
								
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
					if settings['commercials_per_break'] == "auto" and programming_schedule[2] != "commercial" and len(commercials)>0 and tTime != None:
						err_count = 6.3
						#generate a list of commercials that will fill time to the nearest half/top of the hour
						curr_minute = m
						if curr_minute>30:
							err_count = 6.4
							curr_minute = curr_minute - 30

						curr_minute=min(29, curr_minute)
						curr_minute=max(0, curr_minute)

						lenCountDown = 14400
						tTime = (tTime + (curr_minute * 60)) + 30
						
						if tTime == None: # video length was not found, so we revert to random commercials without checking for time
							err_count = 6.5
							commercials_per_break = 0
							report_error("COMM_BREAK", ["length of video could not be found", "SOURCE", str(source)])
						else:
							err_count = 6.6
							lenCountDown = 18000 # set max length of a video (5 hours) and then count down in 30 minute increments
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
							print("length:", tTime, "diff to make up:", tDiff)
							pregenerated_commercials_list = generate_commercials_list(tDiff)
							err_count = 6.9
							err_extra = []
							#print("Pregenerated Commercials:", pregenerated_commercials_list)
							commercials_per_break = 0
					elif programming_schedule[2] == "commercial":
						err_count = 7.0
						commercials_per_break = 0
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