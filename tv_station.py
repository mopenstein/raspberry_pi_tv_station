#!/usr/bin/python

# version: 101.0
# version date: 2021.12.31
# 	Added a setting to automatically set the number of commercials per break to fill time approximately to extend videos to the half hour or full hour. Which should simulate more closely a real broadcast schedule.
# settings version: 0.6

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

def play_video(source, commercials, max_commercials_per_break, start_pos):
	global settings
	if source==None:
		return
	
	try:
		global last_video_played
		global error_count

		current_position = 0;
		
		err_pos = -1.0
		if type(max_commercials_per_break) == list: #if a list of commercials was passed instead of a number, set variables
			gend_commercials = max_commercials_per_break
			if len(gend_commercials)!=0 and len(commercials)!=0:
				max_commercials_per_break = math.ceil(float(len(gend_commercials)) / float(len(commercials))) - 1

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
							except Exception as exce:
								err_pos = err_pos + .02
								#if there was an error playing commercials, report it and resume playing main video
								error_count = error_count + 1
								report_error("COMM_PLAY_LOOP", str(err_pos) + "_" + str(exce))
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
				report_error("COMM_PLAY", str(err_pos) + "_" + str(ecce))
				player.show_video()
				player.play()


		err_pos = 7.0
		#main video has ended
		player.hide_video()
		sleep(0.5)
	except Exception as e:
		if(err_pos!=7.0):
			error_count = error_count + 1
			report_error("PLAY_LOOP", str(err_pos) + "_" + str(e) + "_SOURCE_" + str(source))
		
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
		report_error("COMM_quit", str(err_pos) + "_" + str(ex))
	try:
		player.quit()
	except Exception as exx:
		report_error("PLAY_quit", str(err_pos) + "_" + str(exx))
	
	return

def check_video_times(obj, channel=None, allow_chance=True):
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
			if timeItem['channel'] == None or channel == None:
				skip['channel'] = False
			else:
				skip['channel'] = False if timeItem['channel'] == channel else True

		if 'month' in timeItem:
			if timeItem['month'] != None:
				test = False
				for itemX in timeItem['month']:
					if itemX == month or itemX in ['all','*']: # if it's the proper month, we proceed
						test = True
						break
				skip['month'] = False if test == True else True

		if 'date' in timeItem:
			if timeItem['date'] != None:
				test = False
				for itemX in timeItem['date']:
					if itemX == ddm or itemX in ['all','*']: # if it's the proper month, we proceed
						test = True
						break
				skip['date'] = False if test == True else True

		if 'dayOfWeek' in timeItem:
			if timeItem['dayOfWeek'] != None:
				test = False
				for itemX in timeItem['dayOfWeek']:
					if itemX.lower() == dayOfWeek or itemX in ['all','*']: # if it's the proper day of the week, we proceed
						test = True
						break
				skip['dayOfWeek'] = False if test == True else True
		
		if 'start' in timeItem and 'end' in timeItem:
			if ((h >= timeItem['start'][0] or timeItem['start'][0] in ['all','*']) and (m>=timeItem['start'][1] or timeItem['start'][1] in ['all','*'])) and ((h <= timeItem['end'][0] or timeItem['end'][0] in ['all','*']) and (m <= timeItem['end'][1] or timeItem['end'][1] in ['all','*'])):
				skip['time'] = False
			else:
				skip['time'] = True
	
		if 'special' in timeItem:
			if timeItem['special'] != None:
				skip['special'] = False if is_special_time(timeItem['special']) else True
	
		if 'chance' in timeItem:
			chance_eval = timeItem['chance'] # store chance string
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
			return [timeItem['name'], True if skip['chance']==False else False, video_type]

	return None

def generate_commercials_list(max_time_to_fill):
	start_time = time.time()
	ret = []
	while(True):
		if time.time() - start_time > 5:
			report_error("GEN_COMM_LIST", "took more that 5 seconds to generate comercials list")
			break;
		c = get_random_commercial()
		cTime = get_length_from_file(c)
		if max_time_to_fill - cTime > 40:
			max_time_to_fill = max_time_to_fill - cTime
			ret.append(c)
		elif max_time_to_fill - cTime > 28:
			max_time_to_fill = max_time_to_fill - cTime
			ret.append(c)
			break
		
	return ret

def get_args(index):
	if index < len(sys.argv):
		return sys.argv[index]
	return None 

def get_commercials(source):
	#load single file for all commercials
	#usefull when commercial breaks are easily identifiable for a lot of episodes from a single show
	#first 8 chrs need to exactly the same
	if os.path.isfile(source[:8] + '.commercials.master') == True:
		with open(source[:8] + '.commercials.master') as temp_file:
			return [float(line.rstrip()) for line in temp_file]
	
	if os.path.isfile(source + '.commercials') == True:
		with open(source + '.commercials') as temp_file:
			return [float(line.rstrip()) for line in temp_file]
	return []

def get_length_from_file(file):
	try:
		start = file.index("%T(") + 3
		end = file.index(")%", start)
		if is_number(file[start:end]):
		    return float(file[start:end])
		else:
			return None
	except ValueError:
		return None

def get_random_commercial():
	global settings

	try:
		programming_schedule = check_video_times(settings['commercial_times']) # check to see if any commercials are scheduled
		if programming_schedule == None:
			#no specified commercials were set for this time perioid, even tho the settings suggests there might be
			report_error("GET_RND_COMM", "check settings.json file") #could be intentional, but we're going to report it just in case
			return None

		folder = programming_schedule[0] # the results, if any, returns None type of not
		if folder:
			#commercials are scheduled, so let's gather the videos from the specified paths (could only be 1 or could be many)
			video = get_videos_from_dir(settings['drive'] + replace_all_special_words(folder[0]))
			for itemX in range(1,len(folder)):
				video = video + get_videos_from_dir(settings['drive'] + replace_all_special_words(folder[itemX]))
				
			return random.choice(video) #return a random video from the combined paths
	except:
		#an unknown error occurred, report it and return nothing
		
		report_error("GET_RND_COMM", "##unexpected error## check settings.json file")
		return None	

def get_current_channel():
	global drive
	global settings
	if 'channel_file' not in settings:
		return None
	if settings['channel_file'] == None:
		return None
	if not os.path.exists(settings['channel_file']):
		return None
	if os.path.exists(settings['channel_file']) == False:
		return None
	file = open(settings['channel_file'])
	line = file.read()
	file.close()
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
	results = glob.glob(os.path.join(dir, '*.mp4'))
	results.extend(glob.glob(os.path.join(dir, '*.avi')))
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
	for r in [["%day_of_week%", getDayOfWeek(d)], ["%month%", getMonth(month)]]:
		s = s.replace(*r)
	return s

def report_error(type, message, local_only=False):
	print("#ERROR: " + type + ": " + message)
	if local_only != True:
		contents = open_url("http://127.0.0.1/?error=" + urllib.quote_plus(str(type) + "|" + str(message)))

def restart():
	command = "/usr/bin/sudo /sbin/shutdown -r now"
	process = subprocess.Popen(command.split(), stdout=subprocess.PIPE)
	output = process.communicate()[0]
	print output


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
SETTINGS_VERSION = 0.6

if base_directory != "":
	base_directory = base_directory + "/" if base_directory[-1] != "/" else base_directory

settings_file= base_directory + "settings.json" if TEST_FILE == False else base_directory + "test_settings.json"

f = open(settings_file, "r")
settings = json.loads(f.read())
if 'version' in settings:
	if is_number(settings['version']):
		if float(settings['version']) != SETTINGS_VERSION:
			report_error("Settings", "Settings version mismatch. Things might not work so well.")
	else:
		report_error("Settings", "Settings version mismatch. Things might not work so well.")
else:
	report_error("Settings", "Settings version mismatch. Things might not work so well.")

settings['load_time'] = os.path.getmtime(settings_file)

############################ /settings

################# location of video files

drive = settings['drive']

################# /location of video files

allow_chance = True # We need to decided if we should allow a chance of a video to play as set by our programming in the settings file


########################## main loop

while(1):
	if os.path.getmtime(settings_file) != settings['load_time']:
		# the settings file has been modified, let's reload it
		f = open(settings_file, "r")
		strJson = f.read()
		settings = json.loads(strJson)
		settings['load_time'] = os.path.getmtime(settings_file)
		print("$$$$$$$$$$$ Settings Updated $$$$$$$$$$$$$$$$$$")

	if 'commercials_per_break' not in settings: settings['commercials_per_break'] = 5

	now = datetime.datetime.now() # set the date time
	# check if the settings file has a test date setokay, 
	if 'time_test' in settings:
		if settings['time_test'] != None:
			# if it is, we set the global 'now' var to the test date
			# useful for testing holiday programming and other date/time specific programming
			now = datetime.datetime.strptime(str(settings['time_test']), '%b %d %Y %I:%M%p')
			print("%%%%%%%%%%%%%%%%%%% Using Test Date Time " + str(now))

	# break down the date and time for ease of use
	month = now.month
	h = now.hour
	m = now.minute
	d = now.weekday()
	ddm = now.day

	folder = None					# the main folder(s) the current video will be pulled from

	programming_schedule = check_video_times(settings['times'], get_current_channel(), allow_chance)
	
	folder = programming_schedule[0]

	print('Selected Folder: ', replace_all_special_words(folder[0]))
	if folder != None: # folders have been set, load and play video
		video = None
		if programming_schedule[2] == "video" or programming_schedule[2] == "commercial": # just select a random video out of the path
			video = get_videos_from_dir(drive + replace_all_special_words(folder[0]))
			for itemX in range(1,len(folder)):
				#print('Selected Folder:', replace_all_special_words(folder[0]))
				video = video + get_videos_from_dir(drive + replace_all_special_words(folder[itemX]))
		elif programming_schedule[2] == "show": # select random directory from path (this is to cut down on the time it takes to select a 'show' that hasn't been played yet)
			folders = get_folders_from_dir(drive + replace_all_special_words(folder[0]))
			selfolder = random.choice(folders)
			video = get_videos_from_dir(selfolder)

		if len(video) == 0:
			report_error("PLAY", "video folder contains no videos_SOURCE_" + str(folder[0]))
		
		# if we managed to find some videos, we can proceed	
		if video:
			# we can now select our random video from the folder(s)
			source = random.choice(video)
			#print('Actual file: ', source)
			# load commercial break time stamps for this video (if any)
			commercials = get_commercials(source)
			
			# log it if there's no source file
			if source==None:
				report_error("PLAY", "no source video")
	
			# check if file exists
			#os.path.exists(source)
			acontents = ["default","0","values"]
			
			#if this is the test version of the script, don't report videos to server
			if TEST_FILE == False or get_args(1)=="skip_test":
				# don't check if a video has been recently played if it is a user set programming chance (otherwise it will be compared to the rest of the videos and could be skipped if it was played too recently)
				if not programming_schedule[1] and settings['report_data']:
					#check if this video has been played recently	
					try:
						if programming_schedule[2] == "show" or programming_schedule[2] == "video":
							print("show/video",programming_schedule)
							urlcontents = open_url("http://127.0.0.1/?getshowname=" + urllib.quote_plus(source))
							print("Response: ", urlcontents)
							acontents = urlcontents.split("|")
							last_video_played = acontents[0]
							print("Last video played: " + last_video_played)
						elif programming_schedule[2] == "commercial":
							print("commercial",programming_schedule)
							urlcontents = open_url("http://127.0.0.1/?current_comm=" + urllib.quote_plus(source))
							
					except Exception as exx:
						report_error("SERVER", "server error " + str(exx))
				else:
					acontents = ["Took a chance on a video and it was triggered","0","so we skip this check and just play it"]

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
				if settings['commercials_per_break'] == "auto" and programming_schedule[2] != "commercial" and len(commercials)>0:
					#generate a list of commercials that will fill time to the nearest half/top of the hour
					tTime = get_length_from_file(source) # get video duration from file name
					if tTime == None: # video length was not found, so we revert to random commercials without checking for time
						settings['commercials_per_break'] = 4
					else:
						lenCountDown = 14400 # set max length of a video and then count down in 30 minute increments
						while(lenCountDown>1800):
							#1800 secs (30 mins) is the minimum amount of time to fill
							if tTime > (lenCountDown - 1800):
								break
							#substract 1800 seconds (30 min increments), until we have 30 mins remaining
							lenCountDown = lenCountDown - 1800

						tDiff = lenCountDown - tTime
						#print("length:", tTime, "diff to make up:", tDiff)
						pregenerated_commercials_list = generate_commercials_list(tDiff)
						#print("Pregenerated Commercialas:", pregenerated_commercials_list)
				elif programming_schedule[2] == "commercial":
					commercials_per_break = 0
				else:					
					commercials_per_break = settings['commercials_per_break'] - 1


				if TEST_FILE == True:
					print(source, commercials, commercials_per_break if pregenerated_commercials_list == None else pregenerated_commercials_list, 0, 'chance', programming_schedule[1])
					sleep(3)
				else:
					print('Attempting to play: ', source)
					play_video(source, commercials, commercials_per_break if pregenerated_commercials_list == None else pregenerated_commercials_list, 0)

			print("-------------------------------------------------------------------------------")
			# reboot the pi after playing a video around 3am.
			try:
				now = datetime.datetime.now()
				if (now.hour >= 0 and now.hour <= 5 and get_uptime() > 36000) or error_count >= 20:
					report_error("RESTART_success", "1")
					sleep(0.5)
					restart()
			except Exception as e:
				report_error("RESTART_failed", str(e))

	else:
		print('no folder')


# Commercials format time in seconds to interrupt. Each commercial break is on a new line
#
#	60.0
#	600.545
#	1200.22