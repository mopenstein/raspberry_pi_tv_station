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


def is_number(s):
	try:
		float(s)
		return True
	except ValueError:
		return False

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

def report_error(type, input, local_only=False):
	print(type)
	print(input)

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


def now_totheminute():
	jt_string = str(datetime.datetime.now().day).zfill(2) + "/" + str(datetime.datetime.now().month).zfill(2) + "/" + str(datetime.datetime.now().year).zfill(4) + " " + str(datetime.datetime.now().hour).zfill(2) + ":" + str(datetime.datetime.now().minute).zfill(2)
	return datetime.datetime.strptime(jt_string, "%d/%m/%Y %H:%M")

def wildcard_array():
	return ['all', 'any', '*']

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

def is_special_time(check):
	if check.lower() == 'xmas': return True if PastThanksgiving(False) else False
	if check.lower() == 'thanksgiving': return True if PastThanksgiving(True) else False
	if check.lower() == 'easter': return True if IsEaster() else False
	return False

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

def update_settings():
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

def getDayOfWeek(d):
	return calendar.day_name[d].lower()
		
def getMonth(m):
	if m<1:
		m=m+12
	elif m>12:
		m=m-12
	return [None,'january','february','march','april','may','june','july','august','september','october','november','december'][m]

def parse_vars(instr):
	print("ERROR")

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
					for word in [["day", ddm], ["month", month], ["hour", now_h], ["minute", now_m], ["weekday", now_d]]: # replace special keywords in the chance string
						chance_eval = chance_eval.replace(word[0],str(word[1])+".0")
					chance_eval = eval(chance_eval) # evaluate the math
				if random.random() > float(chance_eval) or allow_chance == False:
					continue

			# between setting checks how many seconds from the first of the current year and compares it to the settings supplied. 
			# this allows relative time comparison down to the second instead of relying on month, day, etc
			# example that would be triggered from 00:00 Jan 01 to 23:59 Dec 31 of the year:
			#	"between": [ [0, 31536000] ] 
			# example that would be triggered from 12:00 Jan 01 to 16:00 Mar 22 AND 00:00 Jun 13 to 00:00 Jul 13 of the year:
			#	"between": [ [43200, 7012800], [14234100, 21837300]  ] 

			if 'between' in timeItem:
				if timeItem['between'] != None:
					test = False
					relativeTime = time.mktime(now.timetuple()) - time.mktime(datetime.datetime.strptime("01/01/" + str(datetime.datetime.now().year).zfill(4) + " 00:00", "%d/%m/%Y %H:%M").timetuple())  # returns how many seconds from 00:00 Jan 1 of the current year
					
					for itemX in timeItem['between']: #if it's a number, compare seconds
						if is_number(itemX[0]):
							if relativeTime >= itemX[0] and relativeTime <= itemX[1]:
								test = True
							else:
								test = False
						else: # if it's a string, convert string to datetime
							if now >= datetime.datetime.strptime(itemX[0] + " " + str(now.year).zfill(4), '%b %d %I:%M%p %Y') and now <= datetime.datetime.strptime(itemX[1] + " " + str(now.year).zfill(4), '%b %d %I:%M%p %Y'):
								test = True
							else:
								test = False
						
					if test==False:
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
		
		
			useThisOne = True
			for itemX in skip:
				if skip[itemX] == True:
					useThisOne = False
					break
			
			if useThisOne:
				return [timeItem['name'], True if skip['chance']==False else False, video_type, is_static, timeItem, now]
	except Exception as valerr:
		report_error("CHECK_TIMES", [str(valerr),traceback.format_exc()])

	return None

now = datetime.datetime.now()
channel_name_static = None
base_directory = os.path.dirname(__file__)

############################ settings
SETTINGS_VERSION = 0.97

if base_directory != "":
	base_directory = base_directory + "/" if base_directory[-1] != "/" else base_directory

settings = None
settings_file = base_directory + "settings.json"

update_settings()

############################ /settings
if len(sys.argv)>1:
	settings['time_test'] = sys.argv[1]

if 'time_test' in settings:
	if settings['time_test'] != None:	
		print("Using Test Date Time " + str(settings['time_test']))

programming_schedule = check_video_times(settings['times'], get_current_channel(), True)

print("<h1>Results</h1><ul>")
for k in programming_schedule[4]:
	print("<b>" + k + "</b>: " + str(programming_schedule[4][k]) + "")
print("</ul>")