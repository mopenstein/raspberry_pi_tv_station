#!/usr/bin/python
import glob						# how we quickly get all files in a directory
import os
from time import sleep			# used to give time for the player to load the video file
import sys
import datetime					# date and time, used for testing purposes

def get_seconds_from_string(stime, add):
	nums = str(stime).split(':')
	if len(nums) == 1:
		return float(stime)
	else:
		dates = datetime.datetime.strptime(stime, "%H:%M:%S")
	return (dates.hour * 3600) + (dates.minute * 60) + int(dates.second) + add

def get_video_duration(filename):
	import subprocess, json
	#ffprobe -v quiet -show_streams -select_streams v:0 -of json ""
	result = subprocess.check_output('ffprobe -v quiet -show_streams -select_streams v:0 -of json "' + filename + '"', shell=True).decode()
	fields = json.loads(result)['streams'][0]
	ret = 0
	add = 0
	try:
		add = int(round(float(fields['tags']['DURATION'][fields['tags']['DURATION'].find("."):len(fields['tags']['DURATION'])])))
		ret = fields['tags']['DURATION'][0:fields['tags']['DURATION'].find(".")]
	except:
		ret = fields['duration']
	
	return get_seconds_from_string(ret, add)

def get_videos_from_dir(dir):

	results = glob.glob(os.path.join(dir, '*.mp4'))
	results.extend(glob.glob(os.path.join(dir, '*.avi')))
	results.extend(glob.glob(os.path.join(dir, '*.mkv')))
	results.extend(glob.glob(os.path.join(dir, '*.mov')))
	results.extend(glob.glob(os.path.join(dir, '*.flv')))
	results.extend(glob.glob(os.path.join(dir, '*.wmv')))
	
	return results

def walk_dir(dir):
	for root, dirs, files in os.walk(dir):
		for dirname in dirs:
			walk_dir(os.path.join(root, dirname))

	handle_files_in_path(get_videos_from_dir(dir))

def handle_files_in_path(files):
	for file in files:
		file_dir =  os.path.dirname(file)
		file_name = os.path.basename(file)
		file_split = os.path.splitext(file_name)
		file_name_no_ext = file_split[0]
		file_name_ext = file_split[1]
		
		if file.find("%T(") == -1 and file.find(")%") == -1:
			flen = "%T(" + str(int(round(get_video_duration(file)))) + ")%"
			new_file = file_dir + "/" + file_name_no_ext + flen + file_name_ext
			if os.path.exists(new_file) == False:
				#file doesn't exist, so we can rename
				print("Renaming: " + new_file)
				os.rename(file, new_file)

			if os.path.exists(file + ".commercials") == True:
				#we should rename the commercials file too
				print("Renaming commercials file!")
				os.rename(file + ".commercials", new_file + ".commercials")
			
				sleep(0.25)
		else:
			print("Duration has already been added to this file!")
			
dir = sys.argv[1]

if os.path.exists(dir) == False:
	print("Path does not exist.")
	quit()

walk_dir(dir)