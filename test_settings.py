import argparse

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
import pprint

output_print = []

def var_dump(variable):
	#pp = pprint.PrettyPrinter(indent=4)
	#pp.pprint(variable)
	output_print.append[{ "var_dump": variable}]

last_channel_name = None;
def get_current_channel():
	global channel_name_static, settings, last_channel_name

	# Return static channel if set
	if channel_name_static is not None:
		return channel_name_static

	# Check if external channel file setting exists
	channels = settings.get('channels')
	if not channels:
		return None

	file_path = channels.get('file')
	if not file_path or not os.path.exists(file_path):
		return None

	with open(file_path) as file:
		line = file.read().strip()  # Strip to remove any extraneous whitespace

	if line != last_channel_name:
		nc = line if line else "default"
		report_error("CHANNEL", ["Channel set to: " + nc])
		last_channel_name = line

	return line if line else None

def is_number(s):
	try:
		float(s)
		return True
	except (TypeError, ValueError):
		return False

def validate_json(json_str):
	try:
		return json.loads(json_str, cls=ReferenceDecoder)
	except ValueError as err:
		return None

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

def report_error(type, input, local_only=False):
	global output_print
	#print(type)
	#print(input)
	if { "type": type, "input": input 	} not in output_print:
		output_print.append({ "type": type, "input": input 	})

def now_totheminute():
	now = datetime.datetime.now()
	jt_string = now.strftime("%d/%m/%Y %H:%M")
	return datetime.datetime.strptime(jt_string, "%d/%m/%Y %H:%M")

def wildcard_array():
	return ['all', 'any', '*']

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
	#target_date = easter_sunday + datetime.timedelta(days=daysFromEaster)
	return is_date_within_range(datetime.date(now.year, now.month, now.day), easter_sunday, daysFromEaster)

from datetime import date, timedelta

def IsMothersDay(daysFromMothersDay):
	global now
	may_first = date(now.year, 5, 1)  # May 1st of the current year
	first_sunday = may_first + timedelta(days=(6 - may_first.weekday() + 7) % 7)  # First Sunday of May
	mothers_day_date = first_sunday + timedelta(weeks=1)  # Second Sunday of May
	#target_date = mothers_day_date + datetime.timedelta(days=daysFromMothersDay)
	return is_date_within_range(datetime.date(now.year, now.month, now.day), mothers_day_date, daysFromMothersDay)

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

	return False

def is_valid_date(date_string):
    try:
        # Define the expected format
        format = "%b %d %Y %I:%M%p"
        
        # Try to parse the date string
        parsed = datetime.datetime.strptime(date_string, format)
        
        # Optional: return True if it matches, False otherwise
        return True
    except:
        return False

def update_current_time():
	global now, settings
	now = now_totheminute()  # Set the date time

	# Check if the settings file has a test date
	test_date = settings.get('time_test')
	if not is_valid_date(test_date) and test_date is not None:
		report_error("TIME/DATE", ["Invalid test date format.", "Date supplied by user: '" + test_date + "'", "Expected format: 'Sep 09 2006 10:00PM'", "Defaulting to current system time."])
		return

	if test_date is not None:
		# If it does, set the global 'now' variable to the test date
		now = datetime.datetime.strptime(str(test_date), '%b %d %Y %I:%M%p')							

def update_settings():
	global settings, settings_file

	try:
		with open(settings_file, "r") as f:
			settings = validate_json(f.read())
	except Exception as e:
		report_error("SETTINGS", ["Could not read settings file: " + str(e)])
		sleep(10)
		exit()

	if settings is None:
		report_error("SETTINGS", ["Settings file isn't valid JSON"])
		sleep(10)
		exit()

	settings['load_time'] = os.path.getmtime(settings_file)

	version = settings.get('version')
	if version:
		if is_number(version):
			if float(version) != SETTINGS_VERSION:
				report_error("Settings", ["Settings version mismatch. Things might not work so well."])
		else:
			report_error("Settings", ["Settings version mismatch. Things might not work so well."])
	else:
		report_error("Settings", ["Settings version mismatch. Things might not work so well."])

def getDayOfWeek(d):
	return calendar.day_name[d].lower()
		
def getMonth(m):
	return ['invalid', 'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'][m % 12]

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

		# Check for percentage format (e.g., "25%") and convert to decimal (e.g., "0.25")
		match = re.match(r'(\d+(?:\.\d+)?)%', equation.strip())
		if match and match.end() == len(equation.strip()):
			num = float(match.group(1))
			if 0.0 <= num <= 100.0:
				return str(num / 100.0)
		

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

		return eval(equation, safe_globals, {})
	except:
		return -1  # if there was an error, return -1

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

def is_dict(var):
	"""
	Checks if the variable is a dictionary.

	:param var: The variable to check.
	:return: True if the variable is a dictionary, False otherwise.
	"""
	return isinstance(var, dict)

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
    import re
    matches = re.findall(r'@([^@]+)@', video_file)
    if matches:
        return matches[-1].split()[-1]
    return None

now = datetime.datetime.now()
channel_name_static = None
base_directory = os.path.dirname(__file__)
current_video_tag = None

############################ settings
SETTINGS_VERSION = 0.994

if base_directory != "":
	base_directory = base_directory + "/" if base_directory[-1] != "/" else base_directory

settings = None
settings_file = base_directory + "settings.json"

update_settings()

############################ /settings
#	if len(sys.argv)>1: # the first argument is the time to test
#	settings['time_test'] = sys.argv[1]
#	if len(sys.argv)>2: # the second argument is the test file which may contain a video tag
#	settings['test_file'] = sys.argv[2]

parser = argparse.ArgumentParser(description='Process video scheduling inputs.')

parser.add_argument('-time', dest='time_test', type=str, help='Time to test in format "Sep 09 2006 10:00:00PM"')
parser.add_argument('-file', dest='test_file', type=str, help='Test file name, e.g., "The man from uncle.mp4"')
parser.add_argument('-tag', dest='video_tag', type=str, help='Optional video tag, e.g., "kids_show"')
parser.add_argument('-chance', dest='chance_test', type=str, help='Test chance evaluated expression, e.g., "0.5 * weekday"')
parser.add_argument('-channel', dest='channel', type=str, help='Sets the current channel, e.g., "Movies"')

args = parser.parse_args()

settings.update({
    'time_test': args.time_test,
    'test_file': args.test_file,
    'video_tag': args.video_tag,
	'chance_test': args.chance_test,
	'channel': args.channel
})
update_current_time()

if settings.get('chance_test', None) != None:
	print(eval_equation(settings['chance_test'], now))
	exit()

#		print("Using Test Date Time " + str(settings['time_test']))
current_video_tag = None

if settings.get('test_file', None) != None:
	current_video_tag = get_video_tag(settings.get('test_file'))

#print("Current Test File: " + str(settings['test_file']))

if settings.get('video_tag', None) != None:
	current_video_tag = settings.get('video_tag')

if settings.get('channel', None) != None:
	report_error("CHANNEL", ["Channel set to: " +  settings.get('channel')])
	channel_name_static = settings.get('channel')

#print("Current Video Tag: " + str(current_video_tag))


output = {}

programming_schedule = check_video_times(settings['times'], get_current_channel(), True)

output['programming'] = {}

if programming_schedule[4]:
	for k in programming_schedule[4]:
		output['programming'][k]= str(programming_schedule[4][k])

	if 'set-tag' in programming_schedule[4]:
		if programming_schedule[4]['set-tag'] != None:
			current_video_tag = programming_schedule[4]['set-tag']
			report_error("TAG", ["Current video tag set to: " + current_video_tag])
else:
	output_print.append({ "PROGRAMMING": "No programming found for the current time." })

programming_schedule = check_video_times(settings['commercial_times'], get_current_channel(), True)
output['commercials'] = {}

if programming_schedule[4]:
	for k in programming_schedule[4]:
		output['commercials'][k] = str(programming_schedule[4][k])
else:
	output_print.append({ "COMMERCIALS": "No commercials found for the current time." })

output['messages'] = output_print
 
print(json.dumps(output))