using System;
using System.Windows.Forms;
using System.Collections.Generic;
using System.Linq;
using System.Diagnostics;
using System.IO;
using System.Drawing;
using System.Text;

namespace ConsoleApplication1
{
    class Program
    {

        static string[] video_file_extensions = new string[] { ".3g2", ".3gp", ".asf", ".avi", ".flv", ".h264", ".m2t", ".m2ts", ".m4a", ".m4v", ".mkv", ".mod", ".mov", ".mp3", ".ogg", "wav", ".mp4", ".mpg", ".png", ".tod", ".ts", ".vob", ".webm", ".wmv" };

        static List<string> GetFiles(string folder, string[] ext_filter = null, string[] name_filter = null)
        {
            if (!Directory.Exists(folder)) return null;
            if (folder == "") return new List<string>();
            List<string> str = new List<string>();
            int i = 0;
            foreach (string file in Directory.EnumerateFiles(folder, "*.*"))
            {
                string fe = Path.GetExtension(file.ToString());
                bool add = false;
                //only include extensions that match ext_filter

                if (ext_filter != null)
                {
                    foreach (string f in ext_filter)
                    {
                        if (fe.ToString().ToLower() == f.ToLower()) add = true;
                    }
                }
                else add = true;

                //filter file names that meet name_filter
                if (add)
                {
                    string fn = Path.GetFileNameWithoutExtension(file.ToString());
                    if (name_filter != null)
                    {
                        foreach (string f in name_filter)
                        {
                            if (fn.ToString().IndexOf(f) > -1) add = false;
                        }
                    }
                }
                if (add) str.Add(file.ToString());

                i++;
            }
            return str;
        }

        static string ffmpeg_location = "";
        static string ffmpegX_location = "";
        static string temp_folder = "";

        static bool Normalizing = false;

        static TimeSpan[] NormalizeAudio(string file, bool Verbose = false)
        {
            Normalizing = true;
            if (Verbose) Console.WriteLine("Normalizing has begun!");
            TimeSpan[] ret = { TimeSpan.FromSeconds(0), TimeSpan.FromSeconds(0) };

            string pad = "_NA_";
            string path = Path.GetDirectoryName(file);
            string file_name = Path.GetFileNameWithoutExtension(file);
            string ext_name = Path.GetExtension(file);
            if (Verbose) Console.WriteLine("Checking if file exists " + path + "\\" + file_name + pad + "" + ext_name);
            if (File.Exists(path + "\\" + file_name + pad + "" + ext_name) || file.IndexOf(pad) >= 0)
            {
                if (Verbose) Console.WriteLine("File exists skipping");
                AddLog("File Exists, skipping normaliztion");
                return ret;
            }

            if (Verbose) Console.WriteLine("Starting new process");
            Process proc = new Process();
            proc.StartInfo.FileName = ffmpegX_location;
            if (Verbose) Console.WriteLine("Setting file location");
            proc.StartInfo.Arguments = "-y -i \"" + file + "\" -af loudnorm=I=-16:TP=-1.5:LRA=11:print_format=json -f null /dev/null";
            if (Verbose) Console.WriteLine("Assigning Arguments");
            proc.StartInfo.RedirectStandardError = true;
            proc.StartInfo.CreateNoWindow = false;
            proc.StartInfo.UseShellExecute = false;

            if (Verbose) Console.WriteLine("Starting process");
            if (!proc.Start())
            {
                Console.WriteLine("Error starting");
            }
            if (Verbose) Console.WriteLine("Process started");
            StreamReader reader = proc.StandardError;
            string line;
            string input_i = "";
            string input_lra = "";
            string input_tp = "";
            string input_thresh = "";
            string input_offset = "";

            Console.WriteLine("Generating Filter...");
            if(!Verbose) Console.CursorVisible = false;

            TimeSpan procTDur = new TimeSpan();
            DateTime start = DateTime.Now;
            if (Verbose) Console.WriteLine("Begining to read lines");
            while ((line = reader.ReadLine()) != null)
            {
                string dur = "";
                int a = line.IndexOf("Duration");
                int x = line.IndexOf("Segment");
                if (a >= 0 && x == -1)
                {
                    int b = line.IndexOf(",", a + 1);
                    dur = line.Substring(a + 10, b - a - 10);
                    string[] durs = dur.Split(':');
                    procTDur = new TimeSpan(0, int.Parse(durs[0]), int.Parse(durs[1]), int.Parse(durs[2].Split('.')[0]), int.Parse(durs[2].Split('.')[1]));
                }

                int percent = 0;

                try
                {
                    string find = "time=";
                    a = line.IndexOf(find);
                    if (a >= 0)
                    {
                        int b = line.IndexOf(" ", a + 1);
                        dur = line.Substring(a + find.Length, b - a - find.Length);
                        string[] durs = dur.Split(':');
                        TimeSpan tdur = new TimeSpan(0, int.Parse(durs[0]), int.Parse(durs[1]), int.Parse(durs[2].Split('.')[0]), int.Parse(durs[2].Split('.')[1]));
                        percent = (int)((tdur.TotalSeconds / procTDur.TotalSeconds) * 100);
                    }
                }
                catch
                { }

                string pbar = "";
                for (int t = 0; t < 50; t++)
                {
                    if (t <= Math.Floor((double)percent / 2))
                    {
                        pbar += "█";
                    }
                    else
                    {
                        pbar += "░";
                    }
                }


                Console.Write(pbar);
                Console.WriteLine();

                try
                {
                    Console.SetCursorPosition(0, Console.CursorTop - 1);
                }
                catch { }

                a = 0;
                string phrase = "";

                phrase = "\"input_i\" : \"";
                a = line.IndexOf(phrase);
                x = line.IndexOf("Segment");
                if (a >= 0 && x == -1)
                {
                    int b = line.IndexOf("\"", a + phrase.Length + 1);
                    input_i = line.Substring(a + phrase.Length, b - a - phrase.Length);
                    AddLog("Found input_i: " + line.Substring(a + phrase.Length, b - a - phrase.Length));
                    //Console.WriteLine("Found input_i: " + line.Substring(a + phrase.Length, b - a - phrase.Length));

                }

                phrase = "\"input_lra\" : \"";
                a = line.IndexOf(phrase);
                if (a >= 0)
                {
                    int b = line.IndexOf("\"", a + phrase.Length + 1);
                    input_lra = line.Substring(a + phrase.Length, b - a - phrase.Length);
                    AddLog("Found input_lra: " + line.Substring(a + phrase.Length, b - a - phrase.Length));
                    //Console.WriteLine("Found input_lra: " + line.Substring(a + phrase.Length, b - a - phrase.Length));
                }

                phrase = "\"input_tp\" : \"";
                a = line.IndexOf(phrase);
                if (a >= 0)
                {
                    int b = line.IndexOf("\"", a + phrase.Length + 1);
                    input_tp = line.Substring(a + phrase.Length, b - a - phrase.Length);
                    AddLog("Found input_tp: " + line.Substring(a + phrase.Length, b - a - phrase.Length));
                    //Console.WriteLine("Found input_tp: " + line.Substring(a + phrase.Length, b - a - phrase.Length));
                }

                phrase = "\"input_thresh\" : \"";
                a = line.IndexOf(phrase);
                if (a >= 0)
                {
                    int b = line.IndexOf("\"", a + phrase.Length + 1);
                    input_thresh = line.Substring(a + phrase.Length, b - a - phrase.Length);
                    AddLog("Found input_thresh: " + line.Substring(a + phrase.Length, b - a - phrase.Length));
                    //Console.WriteLine("Found input_thresh: " + line.Substring(a + phrase.Length, b - a - phrase.Length));
                }

                phrase = "\"target_offset\" : \"";
                a = line.IndexOf(phrase);
                if (a >= 0)
                {
                    int b = line.IndexOf("\"", a + phrase.Length + 1);
                    input_offset = line.Substring(a + phrase.Length, b - a - phrase.Length);
                    AddLog("Found input_offset: " + line.Substring(a + phrase.Length, b - a - phrase.Length));
                    //Console.WriteLine("Found input_offset: " + line.Substring(a + phrase.Length, b - a - phrase.Length));
                }
            }

            //ffmpeg -i in.wav -af loudnorm=I=-16:TP=-1.5:LRA=11:
            //measured_I =-27.61:
            //measured_LRA =18.06:
            //measured_TP=-4.47:
            //measured_thresh=-39.20:
            //offset=0.58:linear=true:print_format=summary -ar 48k out.wav
            //
            proc.Close();



            string filter = "loudnorm=I=-16:TP=-1.5:LRA=11:measured_I=" + input_i + ":measured_LRA=" + input_lra + ":measured_TP=" + input_tp + ":measured_thresh=" + input_thresh + ":offset=" + input_offset + ":linear=true:print_format=summary";

            proc.StartInfo.FileName = ffmpegX_location;
            //
            proc.StartInfo.Arguments = "-y -i \"" + file + "\" -vcodec copy -af " + filter + (file.ToLower().IndexOf(".mp3") > 0 ? " -map_metadata 0 -id3v2_version 3 -write_id3v1 1 " : "") + " \"" + path + "\\" + file_name + pad + "" + ext_name + "\"";
            //Console.WriteLine(proc.StartInfo.Arguments.ToString());
            proc.StartInfo.RedirectStandardError = true;
            proc.StartInfo.UseShellExecute = false;

            if (!proc.Start())
            {
                Console.WriteLine("Error starting");
            }
            reader = proc.StandardError;

            try
            {
                Console.SetCursorPosition(0, Console.CursorTop + 2);
            }
            catch { }

            TimeSpan total = TimeSpan.FromTicks(DateTime.Now.Ticks - start.Ticks);

            ret[0] = total;
            Console.WriteLine("Finished Generating Filter. Took " + total.ToString());

            Console.WriteLine();

            Console.WriteLine("Applying audio filter...");
            start = DateTime.Now;
            while ((line = reader.ReadLine()) != null)
            {

                //Console.WriteLine(line);
                string dur = "";
                int a = line.IndexOf("Duration");
                int x = line.IndexOf("Segment");
                if (a >= 0 && x == -1)
                {
                    int b = line.IndexOf(",", a + 1);
                    dur = line.Substring(a + 10, b - a - 10);
                    string[] durs = dur.Split(':');
                    procTDur = new TimeSpan(0, int.Parse(durs[0]), int.Parse(durs[1]), int.Parse(durs[2].Split('.')[0]), int.Parse(durs[2].Split('.')[1]));
                    Console.WriteLine(procTDur.ToString());
                }

                int percent = 0;

                try
                {
                    string find = "time=";
                    a = line.IndexOf(find);
                    if (a >= 0)
                    {
                        int b = line.IndexOf(" ", a + 1);
                        dur = line.Substring(a + find.Length, b - a - find.Length);
                        string[] durs = dur.Split(':');
                        TimeSpan tdur = new TimeSpan(0, int.Parse(durs[0]), int.Parse(durs[1]), int.Parse(durs[2].Split('.')[0]), int.Parse(durs[2].Split('.')[1]));
                        percent = (int)((tdur.TotalSeconds / procTDur.TotalSeconds) * 100);
                    }
                }
                catch
                { }

                string pbar = "";
                for (int t = 0; t < 50; t++)
                {
                    if (t <= Math.Floor((double)percent / 2))
                    {
                        pbar += "█";
                    }
                    else
                    {
                        pbar += "░";
                    }
                }


                Console.Write(pbar);
                Console.WriteLine();

                try
                {
                    Console.SetCursorPosition(0, Console.CursorTop - 1);
                }
                catch { }

            }

            //Console.ReadKey();
            try
            {
                Console.SetCursorPosition(0, Console.CursorTop + 1);
            }
            catch { }


            total = TimeSpan.FromTicks(DateTime.Now.Ticks - start.Ticks);
            Console.WriteLine();
            ret[1] = total;
            Console.WriteLine("Finished Applying Filter. Took " + total.ToString());

            Console.WriteLine();

            if (File.Exists(file + ".commercials"))
            {
                Console.WriteLine();
                Console.WriteLine("Renamed commercials file!");
                File.SetAttributes(file + ".commercials", FileAttributes.Normal);
                File.Move(file + ".commercials", path + "\\" + file_name + pad + "" + ext_name + ".commercials");
            }

            //make sure the current file and the audio adjusted file exists
            if (File.Exists(file) && File.Exists(path + "\\" + file_name + pad + "" + ext_name))
            {
                Console.WriteLine();
                Console.WriteLine("Deleted original file!");
                File.SetAttributes(file, FileAttributes.Normal);
                File.Delete(file);
            }


            Console.WriteLine();
            proc.Close();
            if (!Verbose) Console.CursorVisible = true;
            Normalizing = false;
            return ret;

        }

        static void AddTimeStringToFileName(string file)
        {


            string padL = "%T(";
            string padR = ")%";

            string path = Path.GetDirectoryName(file);
            string file_name = Path.GetFileNameWithoutExtension(file);
            string ext_name = Path.GetExtension(file);


            if (file.IndexOf(padL) >= 0 && file.IndexOf(padR) >= 0)
            {
                Console.WriteLine("Time already added, skipping!");
                return;
            }

            Console.Write("Getting video length: " + file_name + " ... ");
            file_name = ReturnCleanASCII(file_name); // since we're here, let's remove non-ascii characters. It will save a possible headache later
            TimeSpan t = getVideoDuration(file);
            Console.Write(t.ToString());
            Console.WriteLine("");
            string pad = padL + Math.Round(t.TotalSeconds).ToString() + padR;

            if (File.Exists(path + "\\" + file_name + pad + "" + ext_name))
            {
                Console.WriteLine("File Exists, skipping!");
                return;
            }
            int count = 0;
            while (true)
            {
                    try
                    {
                        Console.Write("Renaming file...");
                        File.SetAttributes(file, FileAttributes.Normal);
                        File.Move(file, path + "\\" + file_name + pad + "" + ext_name);
                        if (File.Exists(path + "\\" + file_name + pad + "" + ext_name)) break;
                    }
                    catch (Exception e)
                    {
                        Console.Write(count.ToString()+"... ");
                        System.Threading.Thread.Sleep(1000);
                    }
                count++;
                if (count > 15) break;

            }

            if (File.Exists(file + ".commercials"))
            {
                Console.WriteLine();
                Console.WriteLine("Renamed commercials file!");
                File.SetAttributes(file + ".commercials", FileAttributes.Normal);
                File.Move(file + ".commercials", path + "\\" + file_name + pad + "" + ext_name + ".commercials");
            }
            Console.WriteLine("Done!");
            Console.WriteLine("");
        }

        static void checkCommercials()
        {
            Process proc = new Process();
            proc.StartInfo.FileName = ffmpeg_location;
            string folder = Path.GetDirectoryName(ffmpeg_location) + "\\convert\\";
            string output_folder = Path.GetDirectoryName(ffmpeg_location) + "\\output\\";

            //foreach (string file in Directory.EnumerateFiles(folder, "*.*"))
            //{
            //File.Move(file.ToString(), folder + Path.GetFileName(file.ToString().Replace(" ", "")));
            //}

            //get duration
            string line;

            foreach (string file in Directory.EnumerateFiles(folder, "*.*"))
            {
                //convert commercial

                string new_file = output_folder + Path.GetFileNameWithoutExtension(file.ToString()) + ".mp4";

                if (File.Exists(new_file) == false)
                {
                    string[] temp = getDurationAndAudioFilter(file);
                    Console.WriteLine(temp[1]);
                    //proc.StartInfo.Arguments = "-i " + "\"" + filename + "\"" + " -bsf:v h264_mp4toannexb -f mpegts " + audio_filter + " -vf  \"" + filter + "scale=640:480\" -r 29.97 -y -b:v 2M -ss " + breaks[i - 1].Hours + ":" + breaks[i - 1].Minutes + ":" + breaks[i - 1].Seconds + " -t 00:" + length.Minutes + ":" + length.Seconds + " " + temp_folder + "\\temp" + i.ToString() + ".mpg";
                    //proc.StartInfo.Arguments = "-i " + "\"" + file.ToString() + "\"" + "  -bsf:v h264_mp4toannexb -f mpegts " + temp[1] + " -vf scale=640:480 -r 29.97 -y -b:v 2M " + "\"" + new_file + "\"";
                    proc.StartInfo.Arguments = "-i \"" + file.ToString() + "\" -vcodec libx264 -crf 23 -s 640x480 -aspect 640:480 -r 29.97 -threads 4 -acodec libvo_aacenc -ab 128k -ar 32000 -async 32000 -ac 2 -scodec copy \"" + new_file + "\"";
                    //proc.StartInfo.Arguments = "-i " + file.ToString() + " -c:v libx264 -preset slow -crf 22 -c:a copy " + new_file;
                    proc.StartInfo.RedirectStandardError = true;
                    proc.StartInfo.UseShellExecute = false;
                    if (!proc.Start())
                    {
                        Console.WriteLine("Error starting");
                        return;
                    }
                    StreamReader reader = proc.StandardError;
                    reader = proc.StandardError;
                    while ((line = reader.ReadLine()) != null)
                    {
                        Console.WriteLine(line);
                    }
                    proc.Close();
                    //                    File.Delete(file.ToString());
                }

            }
        }

        static void checkSplits(string in_path, string out_path, double threshhold, double black_level)
        {
            Process proc = new Process();
            proc.StartInfo.FileName = ffmpeg_location;
            string folder = in_path + "\\";
            string output_folder = out_path;

            //get duration
            string line;
            StreamReader reader;
            Random rnd = new Random();

            foreach (string file in Directory.EnumerateFiles(folder, "*.*"))
            {
                if (Path.GetExtension(file).ToLower() != ".txt")
                {
                    //convert 
                    bool auto_split = true;
                    /*
                    string[] lines = { };
                    if(File.Exists(folder + Path.GetFileNameWithoutExtension(file) + ".txt")==true)
                    {
                        lines = File.ReadAllLines(folder + Path.GetFileNameWithoutExtension(file) + ".txt");
                        auto_split = false;
                    }

                    if (lines[0] == "autodetect" || auto_split == true )
                    */
                    if (auto_split == true)
                    {

                        //List<TimeSpan> breaks = scanForCommercialBreaks(file, threshhold, 0, 0);
                        List<TimeSpan> breaks = NEWscanForCommercialBreaks(file, threshhold, 0, 0, 0, black_level);

                        foreach (TimeSpan tss in breaks)
                        {
                            Console.WriteLine(tss.ToString());
                        }

                        //Console.ReadKey();

                        TimeSpan interval = new TimeSpan();
                        breaks.Add(interval);
                        foreach (TimeSpan aa in breaks)
                        {
                            Console.WriteLine(aa.ToString());
                            AddLog("breaks: " + aa.ToString());
                        }
                        int times = breaks.Count - 1;
                        for (int i = 1; i <= times; i++)
                        {
                            AddLog("Autodetected commercial. Splitting video #" + i.ToString());
                            
                            //TimeSpan length = breaks[i] - breaks[i - 1] - TimeSpan.ParseExact("00:00:00.500", @"hh\:mm\:ss\.fff", null);
                            TimeSpan start = breaks[i - 1];
                            string startStr = $"{start.Hours:D2}:{start.Minutes:D2}:{start.Seconds:D2}.{start.Milliseconds:D3}";

                            string inputPath = $"\"{file}\"";

                            TimeSpan length = breaks[i] - breaks[i - 1] - TimeSpan.FromMilliseconds(333);
                            string lengthStr = $"{length.Hours:D2}:{length.Minutes:D2}:{length.Seconds:D2}.{length.Milliseconds:D3}";

                            string outputName = $"{Path.GetFileNameWithoutExtension(file)}_{i:D3}_{rnd.Next(1, 999):D3}.mp4";
                            string outputPath = $"\"{Path.Combine(output_folder, outputName)}\"";

                            proc.StartInfo.Arguments = $"-ss {startStr} -i {inputPath} -c:v libx264 -r 29.97 -preset slow -b:v 800 -crf 29.97 -vf \"scale=640:480\" -t {lengthStr} {outputPath}";
                            //proc.StartInfo.Arguments = "-ss " + breaks[i - 1].Hours.ToString().PadLeft(2, '0') + ":" + breaks[i - 1].Minutes.ToString().PadLeft(2, '0') + ":" + breaks[i - 1].Seconds.ToString().PadLeft(2, '0') + "." + breaks[i - 1].Milliseconds + " -i " + "\"" + file + "\" -c:v libx264 -r 29.97 -preset slow -b:v 800 -crf 29.97 -vf \"scale=640:480\" -t " + length.Hours.ToString().PadLeft(2, '0') + ":" + length.Minutes.ToString().PadLeft(2, '0') + ":" + length.Seconds.ToString().PadLeft(2, '0') + "." + length.Milliseconds + " \"" + output_folder + Path.GetFileNameWithoutExtension(file) + "_" + i.ToString("D3") + "_" + rnd.Next(1, 999).ToString("D3") + ".mp4\"";


                            //proc.StartInfo.Arguments = "-ss " + breaks[i - 1].Hours.ToString().PadLeft(2, '0') + ":" + breaks[i - 1].Minutes.ToString().PadLeft(2, '0') + ":" + breaks[i - 1].Seconds.ToString().PadLeft(2, '0') + "." + breaks[i - 1].Milliseconds + " -i " + "\"" + file + "\" -c:v copy -c:a copy -t " + length.Hours.ToString().PadLeft(2, '0') + ":" + length.Minutes.ToString().PadLeft(2, '0') + ":" + length.Seconds.ToString().PadLeft(2, '0') + "." + length.Milliseconds + " \"" + output_folder + Path.GetFileNameWithoutExtension(file) + "_" + i.ToString() + "_" + rnd.Next(1, 999).ToString() + ".mp4\"";
                            Console.WriteLine(proc.StartInfo.Arguments);

                            AddLog("Split command:" + proc.StartInfo.Arguments.ToString());
                            proc.StartInfo.RedirectStandardError = true;
                            proc.StartInfo.UseShellExecute = false;
                            if (!proc.Start())
                            {
                                Console.WriteLine("Error starting");
                                return;
                            }

                            reader = proc.StandardError;
                            while ((line = reader.ReadLine()) != null)
                            {
                                Console.WriteLine(line);
                            }
                            proc.Close();
                        }
                    }
                    /*
                    else
                    {
                        TimeSpan start = new TimeSpan();
                        TimeSpan stop = new TimeSpan();
                        TimeSpan diff = new TimeSpan();
                        string[] temp = getDurationAndAudioFilter(file);

                        Console.WriteLine("AudioFilter: " + temp[1]);
                        //Console.ReadKey();

                        for (int z = 1; z < lines.Length; z++)
                        {
                            start = TimeSpan.Parse(lines[z - 1]);
                            stop = TimeSpan.Parse(lines[z]);
                            diff = stop - start;
                            string start_str = "00:" + lines[z - 1];
                            string length_str = "00:" + diff.ToString().Substring(0, diff.ToString().LastIndexOf(":"));



                            proc.StartInfo.Arguments = "-i " + "\"" + file + "\"" + " -bsf:v h264_mp4toannexb -f mpegts " + temp[1] + " -vf scale=640:480 -r 29.97 -y -b:v 2M -ss " + start_str + " -t " + length_str + " " + output_folder + Path.GetFileNameWithoutExtension(file).ToString() + rnd.Next(1, 9999).ToString() + ".mpg";
                            proc.StartInfo.RedirectStandardError = true;
                            proc.StartInfo.UseShellExecute = false;
                            if (!proc.Start())
                            {
                                Console.WriteLine("Error starting");
                                return;
                            }
                            reader = proc.StandardError;
                            while ((line = reader.ReadLine()) != null)
                            {
                                Console.WriteLine(line);
                            }
                            proc.Close();

                        }
                    }
                    */
                }
            }
        }

        static string[] getDurationAndAudioFilter(string file)
        {
            Process proc = new Process();
            proc.StartInfo.FileName = ffmpeg_location;

            string fname_noext = Path.GetFileNameWithoutExtension(file);
            string fname_root = Path.GetDirectoryName(file);

            string filename = file;
            string audio_filter = "";
            string dur = "";
            string resolution = "";
            AddLog("Getting Duration and Audio Filter string for " + file.ToString());
            //get duration
            proc.StartInfo.Arguments = "-i " + "\"" + filename + "\"" + " -vf \"blackdetect=d=2:pix_th=0.00\" -af volumedetect -f null -";
            proc.StartInfo.RedirectStandardError = true;
            proc.StartInfo.UseShellExecute = false;
            if (!proc.Start())
            {
                Console.WriteLine("Error starting");
            }
            StreamReader reader = proc.StandardError;
            string line;
            while ((line = reader.ReadLine()) != null)
            {
                int a = line.IndexOf("Duration");
                int xx = line.IndexOf("Durations");
                if (a >= 0 && xx == -1)
                {
                    int b = line.IndexOf(".", a + 1);
                    dur = line.Substring(a + 10, b - a - 10);
                    AddLog("Found length " + dur.ToString());
                    //Console.WriteLine("Found length " + dur.ToString());
                    //return new string[] { dur, "5" };
                }

                a = line.IndexOf("Stream"); //get resolution of video
                if (a >= 0)
                {
                    string[] parts = line.Split(new string[] { ", " }, StringSplitOptions.RemoveEmptyEntries);
                    foreach (string dd in parts)
                    {
                        if (dd.IndexOf("x") >= 0) resolution = dd;
                    }
                }

                a = line.IndexOf("max_volume: ");
                if (a >= 0)
                {
                    int b = line.IndexOf("dB", a + 1);

                    string num = line.Substring(a + 12, b - a - 13);
                    double max_volume = Double.Parse(num, System.Globalization.NumberStyles.Any);
                    if (max_volume >= 0) //too loud
                    {
                        AddLog("Audio is too loud, decreasing by " + Math.Abs(max_volume).ToString() + "dB");
                        double mean = -max_volume;
                        audio_filter = "-af \"volume=-" + Math.Abs(mean).ToString() + "dB\" ";
                    }
                    else
                    {
                        AddLog("Audio is too low, increasing by " + Math.Abs(max_volume).ToString() + "dB");
                        double mean = max_volume;
                        audio_filter = "-af \"volume=" + Math.Abs(mean).ToString() + "dB\" ";
                    }

                    //                    Console.WriteLine(audio_filter);
                    //Console.ReadKey();
                }
                //Console.WriteLine(line);
            }
            //Console.ReadKey();
            proc.Close();
            AddLog("Filter: " + audio_filter);
            return new string[] { dur, audio_filter };
        }

        static void joinConcat(string concat, string output_name = null)
        {
            emptyTemp();
            Process proc = new Process();
            proc.StartInfo.FileName = ffmpeg_location;
            Random rnd = new Random();
            string file = "random_commercials_" + rnd.Next(1, 9999).ToString() + ".mpg";
            if (output_name != null) file = output_name;
            string fname_noext = Path.GetFileNameWithoutExtension(file);
            string fname_root = Path.GetDirectoryName(file);
            string output_folder = Path.GetDirectoryName(ffmpeg_location) + "\\output\\";

            AddLog("CONCAT string generated: " + concat);
            //Console.WriteLine("-i \"concat:" + concat + "\" -c copy -f mpegts -analyzeduration 2147483647 -probesize 2147483647 -y -b:v 2M " + output_folder + fname_noext + ".mpg");
            //Console.ReadKey();
            //proc.StartInfo.Arguments = "-i \"concat:" + concat + "\" -c copy -f mpegts -analyzeduration 2147483647 -probesize 2147483647 -y -b:v 2M " + output_folder + fname_noext + ".mpg";
            //proc.StartInfo.Arguments = "-i \"concat:" + concat + "\" -c:v libx264 -r 29.97 -preset slow -b:v 800 -crf 28 -c:a copy -vf \"scale=640:480,setdar=4:3\" " + "\"" + output_folder + fname_noext + ".mp4" + "\"";
            //proc.StartInfo.Arguments = concat + " -c:v libx264 -r 29.97 -preset slow -b:v 800 -crf 28 -c:a copy -vf \"scale=640:480,setdar=4:3\" " + "\"" + output_folder + fname_noext + ".mp4" + "\"";
            Console.Write("SSSS" + concat);
            //Console.ReadKey();
            File.WriteAllText("mylist.txt", concat);
            Console.Write("AAAAAAAA" + File.ReadAllText("mylist.txt"));
            //Console.ReadKey();
            proc.StartInfo.Arguments = "-f concat -safe 0 -i mylist.txt -c:v libx264 -r 29.97 -preset slow -b:v 800 -crf 28 -c:a copy -vf \"scale=640:480,setdar=4:3\" " + "\"" + output_folder + fname_noext + ".mp4" + "\"";


            AddLog(proc.StartInfo.Arguments);
            Console.Write(proc.StartInfo.Arguments);
            //Console.ReadKey();
            proc.StartInfo.RedirectStandardError = true;
            proc.StartInfo.UseShellExecute = false;
            AddLog("Merging all files and commercials...");
            if (!proc.Start())
            {
                Console.WriteLine("Error starting");
                return;
            }
            StreamReader reader = proc.StandardError;
            string line;
            while ((line = reader.ReadLine()) != null)
            {
                Console.WriteLine(line);
            }
            proc.Close();
            AddLog("Merge complete.");
            AddLog("Finished converting " + file.ToString());
            Console.WriteLine("Finished!");
        }

        static string getScreenShot(string file, string pos)
        {
            /*
            if (File.Exists(temp_folder + "\\screen_" + TimeSpan.Parse(pos).TotalSeconds.ToString() + ".jpg"))
            {
                return temp_folder + "\\screen_" + TimeSpan.Parse(pos).TotalSeconds.ToString() + ".jpg";
            }
            */

            if (!File.Exists(file))
            {
                return null;
            }

            Process proc = new Process();
            proc.StartInfo.FileName = ffmpeg_location;

            string fname_noext = Path.GetFileNameWithoutExtension(file);
            string fname_root = Path.GetDirectoryName(file);

            string filename = file;

            //get duration
            //proc.StartInfo.Arguments = "-ss " + pos + " -i " + "\"" + filename + "\" -vframes 1 -q:v 2 \"" + temp_folder + "\\screen_" + TimeSpan.Parse(pos).TotalSeconds.ToString() + ".jpg\"";

            /*
            if (File.Exists(temp_folder + "\\screen_" + pos + ".jpg"))
            {
                File.SetAttributes(temp_folder + "\\screen_" + pos + ".jpg", FileAttributes.Normal);
                File.Delete(temp_folder + "\\screen_" + pos + ".jpg");
            }
            */

            Random rnd = new Random();
            string temp_file = temp_folder + "\\screen_" + pos + "_" + rnd.Next(1, 999).ToString() + "_" + rnd.Next(1, 999).ToString() + ".jpg";
            proc.StartInfo.Arguments = "-y -ss " + pos + " -i " + "\"" + filename + "\" -vframes 1 -q:v 2 \"" + temp_file;
            AddLog(proc.StartInfo.Arguments);
            proc.StartInfo.RedirectStandardError = true;
            proc.StartInfo.RedirectStandardOutput = true;
            proc.StartInfo.UseShellExecute = false;
            proc.StartInfo.CreateNoWindow = true;

            if (!proc.Start())
            {
                AddLog("Error starting");
            }
            StreamReader reader = proc.StandardError;
            string line;
            while ((line = reader.ReadLine()) != null)
            {
            }
            //Console.ReadKey();
            proc.Close();

            try
            {
                proc.Kill();
            }
            catch { }

            //return temp_folder + "\\screen_" + TimeSpan.Parse(pos).TotalSeconds.ToString() + ".jpg";
            return temp_file;


        }

        static string getVideoOption(string file, string option)
        {
            string fname_noext = Path.GetFileNameWithoutExtension(file);
            string fname_root = Path.GetDirectoryName(file);

            if (File.Exists(fname_root + "\\" + fname_noext + ".options"))
            {
                AddLog("Requesting video specific options...");
                string[] lines = File.ReadAllLines(fname_root + "\\" + fname_noext + ".options");
                for (int i = 0; i < lines.Count(); i++)
                {
                    string[] opt = lines[i].Split(new string[] { "=" }, StringSplitOptions.None);
                    if (opt[0].ToLower() == option.ToLower())
                    {
                        return opt[1];
                    }
                }
            }
            return null;
        }

        static bool IsNumeric(string value)
        {
            return value.All(char.IsNumber);
        }

        static void emptyTemp()
        {
            string folder = temp_folder;
            List<string> files = GetFiles(folder);
            AddLog("Cleaning up temp folder.");
            foreach (string s in files)
            {
                File.Delete(s);
            }
        }

        static void joinVideos()
        {
            emptyTemp();
            Process proc = new Process();
            proc.StartInfo.FileName = ffmpeg_location;
            AddLog("Checking for files to join...");

            string output_folder = Path.GetDirectoryName(ffmpeg_location) + "\\output\\";
            string folder = Path.GetDirectoryName(ffmpeg_location) + "\\join\\";
            string fname_noext = "";
            List<string> files = GetFiles(folder);
            if (files.Count == 0) return;
            string concat = "";
            string line;
            StreamReader reader;

            /*
            AddLog("Renaming join files...");
            foreach (string s in files)
            {
                if (("a" + s).IndexOf(" ") > 0)
                {
                    File.Move(s, s.Replace(" ", "-"));
                }
            }
            */
            files = GetFiles(folder);

            /*

            int i = 0;
            files = files.OrderBy(o => o.ToString()).ToList();
            foreach (string s in files)
            {
                if (Path.GetExtension(s).ToLower() != ".mpg")
                {
                    string[] info = getDurationAndAudioFilter(s);
                    AddLog(i.ToString() + ") Converting " + s);
                    proc.StartInfo.Arguments = "-i " + s + " -bsf:v h264_mp4toannexb -f mpegts -vf scale=640:480 -r 29.97 -y -b:v 2M " + temp_folder + "\\temp" + i.ToString() + ".mpg";
                    proc.StartInfo.RedirectStandardError = true;
                    proc.StartInfo.UseShellExecute = false;
                    if (!proc.Start())
                    {
                        Console.WriteLine("Error starting");
                        return;
                    }
                    reader = proc.StandardError;
                    while ((line = reader.ReadLine()) != null)
                    {
                        Console.WriteLine(line);
                    }
                    proc.Close();
                }
                else
                {
                    Console.WriteLine("Copying file #" + i.ToString());
                    AddLog(i.ToString() + ") No need to convert " + s + ", copying instead");
                    File.Copy(s, "" + temp_folder + "\\temp" + i.ToString() + ".mpg");
                }

                i++;

            }
            files = GetFiles(temp_folder);

            AddLog("Generating CONCAT string...");
            
            foreach (string s in files)
            {
                if (File.Exists(s))
                {
                    fname_noext += Path.GetFileNameWithoutExtension(s) + "_";
                    concat += s + "|";
                }
                else
                {
                    AddLog("Could not find file " + s);
                }

            }
            */

            int i = 0;
            foreach (string s in files)
            {
                if (File.Exists(s))
                {
                    concat += "file " + s.Replace("\\", "\\\\") + "\r\n";
                    i++;
                }
                else
                {
                    AddLog("Could not find file " + s);
                }

            }

            File.WriteAllText(output_folder + "files.txt", concat);

            while (File.Exists(output_folder + "files.txt") == false)
            {
                Console.WriteLine("Waiting for file to write...");
            }


            if (File.Exists(output_folder + "files.txt") == false)
            {
                Console.WriteLine("File no exists!!!!!!!!!!!!!!!!!!");
            }

            concat = concat.Substring(0, concat.Length - 1);
            AddLog("CONCAT string generated: " + concat);
            Console.WriteLine("-i \"concat:" + concat + "\" -c copy -f mpegts -analyzeduration 2147483647 -probesize 2147483647 -y -b:v 2M " + output_folder + "joined_" + i.ToString() + "_files.mpg");
            //Console.ReadKey();
            //proc.StartInfo.Arguments = "-i \"concat:" + concat + "\" -c copy -f mpegts -analyzeduration 2147483647 -probesize 2147483647 -y -b:v 2M " + "\"" + output_folder + "joined_" + i.ToString() + "_files.mpg" + "\"";
            proc.StartInfo.Arguments = "-f concat -i \"" + output_folder + "files.txt\" -c copy \"" + output_folder + "joined_" + i.ToString() + "_files.mp4" + "\"";

            proc.StartInfo.RedirectStandardError = true;
            proc.StartInfo.UseShellExecute = false;
            AddLog("Merging all files and commercials...");
            if (!proc.Start())
            {
                Console.WriteLine("Error starting");
                return;
            }
            reader = proc.StandardError;
            while ((line = reader.ReadLine()) != null)
            {
                Console.WriteLine(line);
            }
            proc.Close();
            Console.WriteLine("Cleaning up temp files...");
            AddLog("Merge complete. Cleaning up temp files...");
            AddLog("Finished converting " + fname_noext.ToString());
            Console.WriteLine("Finished!");
            return;

        }

        static TimeSpan getVideoDuration(string file)
        {
            Process proc = new Process();
            proc.StartInfo.FileName = ffmpeg_location;

            string filename = file;
            string dur = "";
            AddLog("Getting Duration " + file.ToString());
            //get duration
            proc.StartInfo.Arguments = "-i " + "\"" + filename + "\"" + " -f null -";
            proc.StartInfo.RedirectStandardError = true;
            proc.StartInfo.UseShellExecute = false;
            if (!proc.Start())
            {
                Console.WriteLine("Error starting");
            }
            StreamReader reader = proc.StandardError;
            string line;
            while ((line = reader.ReadLine()) != null)
            {
                int a = line.IndexOf("Duration");
                int xx = line.IndexOf("Durations");
                if (a >= 0 && xx == -1)
                {
                    int b = line.IndexOf(",", a + 1);
                    dur = line.Substring(a + 10, b - a - 10);
                    AddLog("Found length " + dur.ToString());
                    while (true)
                    {
                        try
                        {
                            proc.Kill();
                            break;
                        }
                        catch
                        { }
                    }
                    //proc.Close();
                    return TimeSpan.Parse(dur);
                    //Console.WriteLine("Found length " + dur.ToString());
                    //return new string[] { dur, "5" };
                }

            }
            //Console.ReadKey();
            proc.Close();
            return new TimeSpan();
        }

        static void MoveTaggedFiles(string dir)
        {
            var tags = new Dictionary<string, string> { { "%AM%", "am" }, { "%PM%", "pm" }, { "%ANY%", "any" } };
            Console.WriteLine("Searcing for tagged files in " + dir);
            foreach (var file in Directory.GetFiles(dir))
            {
                var name = Path.GetFileName(file);

                foreach (var tag in tags.Keys)
                {
                    if (name.IndexOf(tag, StringComparison.OrdinalIgnoreCase) >= 0)
                    {
                        
                        var sub = Path.Combine(dir, tags[tag]);
                        Directory.CreateDirectory(sub);
                        Console.WriteLine("Moving file " + file + " to " + Path.Combine(sub, name));
                        File.Move(file, Path.Combine(sub, name));
                        break;
                    }
                }
            }
        }
        static List<TimeSpan> NEWscanForCommercialBreaks(string file, double threshhold, double wait, int min_time_add = 300, int min_end_time = 59, double black_level=0.05, bool addStartEnd = true)
        {
            TimeSpan vid_dur = getVideoDuration(file);

            Process proc = new Process();
            proc.StartInfo.FileName = ffmpeg_location;

            string fname_noext = Path.GetFileNameWithoutExtension(file);
            string fname_root = Path.GetDirectoryName(file);

            string filename = file;

            //get duration
            proc.StartInfo.Arguments = "-i \"" + filename + "\" -vf \"blackdetect=d=" + threshhold.ToString() + ":pix_th=" + black_level.ToString() + "\" -an -f null -";
            AddLog("scanForCommercialBreaks:" + proc.StartInfo.Arguments);

            proc.StartInfo.RedirectStandardError = true;
            proc.StartInfo.UseShellExecute = false;
            if (!proc.Start())
            {
                AddLog("Error starting");
            }
            StreamReader reader = proc.StandardError;
            List<TimeSpan> nums = new List<TimeSpan>();
            if(addStartEnd) nums.Add(TimeSpan.FromSeconds(0));
            
            string line;
            AddLog("Scanning for Commercial Breaks...");
            DateTime now = DateTime.Now;
            //nums.Add(TimeSpan.FromSeconds(0));
            double last_break = 0;
            while ((line = reader.ReadLine()) != null)
            {

                if ((DateTime.Now - now) > TimeSpan.FromSeconds(.25))
                {
                    //drawn a placeholder so the user doesn't think the process locked up
                    Console.Write(".");
                    now = DateTime.Now;
                }

                //AddLog(line.ToString());
                int a = line.IndexOf("blackdetect");
                if (a >= 0)
                {
                    a = line.IndexOf("black_start:");
                    int b = line.IndexOf(" ", a + 1);
                    double bstart = Convert.ToDouble(line.Substring(a + 12, b - a - 12));

                    a = line.IndexOf("black_end:", b + 1);
                    b = line.IndexOf(" ", a + 1);
                    double bend = Convert.ToDouble(line.Substring(a + 10, b - a - 10));

                    a = line.IndexOf("black_duration:", b + 1);
                    b = line.Length;
                    double bdur = Convert.ToDouble(line.Substring(a + 15, b - a - 15));

                    double bmed = bstart + (bdur / 2);

                    //AddLog(bmed + " - " + wait);
                    //commercial breaks must start after the minimum wait time and be less than video length minus minimum end time
                    if (bmed > wait && ((vid_dur.TotalSeconds - bmed) > min_end_time))
                    {
                        //time between breaks must be longer than the set minimum time
                        if (((bmed - last_break) > min_time_add))
                        {
                            nums.Add(TimeSpan.FromSeconds(bmed));
                            last_break = bmed;
                            Console.Write("+" + TimeSpan.FromSeconds(bmed).ToString());
                        }
                        else if (nums.Count == 0) //we should add first break regardless of minimun time difference 
                        {
                            nums.Add(TimeSpan.FromSeconds(bmed));
                            last_break = bmed;
                            Console.Write("+" + TimeSpan.FromSeconds(bmed).ToString());
                        }
                        else
                        {
                            Console.Write("x");
                        }
                    }

                    //AddLog("start: " + TimeSpan.FromSeconds(bstart).ToString() + " end: " + TimeSpan.FromSeconds(bend).ToString() + " median: " + TimeSpan.FromSeconds(bmed).ToString() + " duration: " + bdur);
                }
            }
            Console.WriteLine();
            AddLog("Finished scan..... found " + nums.Count.ToString() + " breaks");
            try
            {
                proc.Kill();
            }
            catch
            {

            }
            try
            {
                proc.Close();
            }
            catch
            {

            }
            //
            if (addStartEnd) nums.Add(TimeSpan.FromSeconds(vid_dur.TotalSeconds));
            return nums;

            //return commerical_breaks;
        }

        static List<TimeSpan> scanForCommercialBreaks(string file, double threshhold, double wait, int min_time_add = 300)
        {
            Process proc = new Process();
            proc.StartInfo.FileName = ffmpegX_location;

            string fname_noext = Path.GetFileNameWithoutExtension(file);
            string fname_root = Path.GetDirectoryName(file);

            string filename = file;

            //get duration
            proc.StartInfo.Arguments = "-i " + "\"" + filename + "\"" + " -vf blackframe -an -f null -";
            AddLog("scanForCommercialBreaks:" + proc.StartInfo.Arguments);
            //proc.StartInfo.Arguments = "-i " + "\"" + filename + "\"" + " -vf select='gte(scene,0)' -an -f null -";

            proc.StartInfo.RedirectStandardError = true;
            proc.StartInfo.UseShellExecute = false;
            if (!proc.Start())
            {
                Console.WriteLine("Error starting");
            }
            StreamReader reader = proc.StandardError;
            List<TimeSpan> nums = new List<TimeSpan>();
            string line;
            string last_num = "";
            Console.WriteLine("Scanning for Commercial Breaks...");
            DateTime now = DateTime.Now;
            while ((line = reader.ReadLine()) != null)
            {
                if ((DateTime.Now - now) > TimeSpan.FromSeconds(.5))
                {
                    Console.Write("_");
                    now = DateTime.Now;
                }
                int a = line.IndexOf("Parsed_blackframe");
                if (a >= 0)
                {
                    a = line.IndexOf(" t:");
                    int b = line.IndexOf(" ", a + 1);
                    string num = line.Substring(a + 3, b - a - 3);
                    if (last_num != "")
                    {
                        //
                        if (Double.Parse(num) - Double.Parse(last_num) > 2) nums.Add(TimeSpan.FromSeconds(0));
                    }
                    last_num = num;
                    //see if the commercial break is before "wait" in seconds, this can be set to avoid going to commercial break too soon after a show starts
                    if (Double.Parse(num) > wait)
                    {
                        nums.Add(TimeSpan.FromSeconds(Double.Parse(num)));
                        Console.Write(".");
                    }
                    else
                    {
                        //black_frame was found before wait time
                        Console.Write("<");
                    }

                }
                else
                {
                    if (nums.Count > 0)
                    {
                        if (nums[nums.Count - 1].TotalSeconds != 0)
                        {
                            nums.Add(TimeSpan.FromSeconds(0));
                            Console.Write("+");
                        }
                    }
                }
            }
            Console.WriteLine("#");
            Console.WriteLine("Confirming Breaks...");
            for (int i = 1; i < nums.Count; i++)
            {
                Console.WriteLine(nums[i - 1]);
                if (nums[i - 1].TotalSeconds == 0 && nums[i].TotalSeconds == 0)
                {
                    nums.RemoveAt(i);
                    Console.Write("-");
                }
            }
            List<TimeSpan> commerical_breaks = new List<TimeSpan>();
            commerical_breaks.Add(TimeSpan.FromSeconds(0));
            int last = 0;
            for (int i = 0; i < nums.Count; i++)
            {
                TimeSpan t = nums[i];
                //Console.WriteLine(i + " - " + t.ToString());
                if (t.TotalSeconds == 0) //start new count
                {
                    if (last != 0)
                    {

                        TimeSpan q = nums[last + 1];
                        TimeSpan qa = nums[i - 1];
                        //Console.WriteLine("Total: " + (qa - q).TotalSeconds + " - " + qa.ToString() + " - " + q.ToString());
                        AddLog("Total: " + (qa - q).TotalSeconds + " - " + qa.ToString() + " - " + q.ToString() + " ticks:" + (qa - q).Ticks + " thresh hold:" + TimeSpan.FromSeconds(threshhold).Ticks);
                        if ((qa - q).Ticks > TimeSpan.FromSeconds(threshhold).Ticks)
                        {

                            //Console.WriteLine("Found commercial break at " + qa.ToString());
                            AddLog("Found commercial break between (" + q.ToString() + "," + qa.ToString() + ")");
                            TimeSpan tdiff = qa - q;
                            AddLog("Using difference: " + (q + new TimeSpan(tdiff.Ticks / 2)).ToString());
                            commerical_breaks.Add((q + new TimeSpan(tdiff.Ticks / 2)));
                            Console.Write("*");
                        }
                        last = i;
                    }
                    else
                    {
                        last = i;
                    }
                }

                //Console.WriteLine(t.ToString());
            }
            Console.WriteLine("#");
            Console.WriteLine("Commercial breaks found: " + commerical_breaks.Count);
            AddLog("Commercial breaks found: " + commerical_breaks.Count);
            //Console.ReadKey();
            proc.Close();

            TimeSpan lt = new TimeSpan(0, 0, 0);
            List<TimeSpan> new_com = new List<TimeSpan>();
            foreach (TimeSpan t in commerical_breaks)
            {
                if (t.TotalSeconds - lt.TotalSeconds > min_time_add || lt.TotalMinutes == 0 || threshhold == 0)
                {
                    new_com.Add(t);
                    AddLog("New Found commercial break at " + t.ToString());
                    //Console.WriteLine("New Found commercial break at " + t.ToString());
                    Console.Write("!");
                }

                lt = t;
            }

            return new_com;

            //return commerical_breaks;
        }

        //Path.GetDirectoryName(ffmpeg_location) + "\\output\\log.txt"
        private static string Log_File = "";

        static void ResetLog()
        {
            try
            {
                if (Log_File == "") return;
                if (File.Exists(Log_File) == false) File.Create(Log_File + "\\log.txt").Close();
                File.WriteAllText(Log_File + "\\log.txt", string.Empty);
            } catch(Exception ex)
            {
                Console.WriteLine(ex.ToString());
            }
        }

        static void AddLog(string log)
        {
            if (Log_File == "") return;
            File.AppendAllText(Log_File + "\\log.txt", DateTime.Now.ToString() + "    " + log + "\r\n");
        }

        static List<TimeSpan> breakz = new List<TimeSpan>();


        static void drawScreen(int which_one)
        {
            Console.Clear();
            //check and convert any non-mpg video to mpg
            switch (which_one)
            {
                case 1:
                    Console.WriteLine(@"  _   _                            _ _         ");
                    Console.WriteLine(@" | \ | |                          | (_)        ");
                    Console.WriteLine(@" |  \| | ___  _ __ _ __ ___   __ _| |_ _______ ");
                    Console.WriteLine(@" | . ` |/ _ \| '__| '_ ` _ \ / _` | | |_  / _ \");
                    Console.WriteLine(@" | |\  | (_) | |  | | | | | | (_| | | |/ /  __/");
                    Console.WriteLine(@" |_| \_|\___/|_|  |_| |_| |_|\__,_|_|_/___\___|");
                    break;
                case 2:
                    Console.Clear();
                    Console.WriteLine("  _____      _       _     ____                 _        ");
                    Console.WriteLine(" |  __ \\    (_)     | |   |  _ \\               | |       ");
                    Console.WriteLine(" | |__) | __ _ _ __ | |_  | |_) |_ __ ___  __ _| | _____ ");
                    Console.WriteLine(" |  ___/ '__| | '_ \\| __| |  _ <| '__/ _ \\/ _` | |/ / __|");
                    Console.WriteLine(" | |   | |  | | | | | |_  | |_) | | |  __/ (_| |   <\\__ \\");
                    Console.WriteLine(" |_|   |_|  |_|_| |_|\\__| |____/|_|  \\___|\\__,_|_|\\_\\___/");
                    break;
                case 3:
                    Console.WriteLine(@"  _________  _     ____ ______      __ __ ____ ___     ___  ___  ");
                    Console.WriteLine(@" / ___/    \| |   |    |      |    |  |  |    |   \   /  _]/   \ ");
                    Console.WriteLine(@"(   \_|  o  ) |    |  ||      |    |  |  ||  ||    \ /  [_|     |");
                    Console.WriteLine(@" \__  |   _/| |___ |  ||_|  |_|    |  |  ||  ||  D  |    _]  O  |");
                    Console.WriteLine(@" /  \ |  |  |     ||  |  |  |      |  :  ||  ||     |   [_|     |");
                    Console.WriteLine(@" \    |  |  |     ||  |  |  |       \   / |  ||     |     |     |");
                    Console.WriteLine(@"  \___|__|  |_____|____| |__|        \_/ |____|_____|_____|\___/ ");
                    break;
                case 4:
                    Console.WriteLine(@" ,----.                  ,-----.                       ,--.            ");
                    Console.WriteLine(@"'  .-./    ,---. ,--,--, |  |) /_,--.--. ,---.  ,--,--.|  |,-.  ,---.  ");
                    Console.WriteLine(@"|  | .---.| .-. :|      \|  .-.  \  .--'| .-. :' ,-.  ||     / (  .-'  ");
                    Console.WriteLine(@"'  '--'  |\   --.|  ||  ||  '--' /  |   \   --.\ '-'  ||  \  \ .-'  `) ");
                    Console.WriteLine(@" `------'  `----'`--''--'`------'`--'    `----' `--`--'`--'`--'`----'  ");
                    break;
                case 5:
                    Console.WriteLine(@" __  __  _____  _  _  ____    ____   __    ___   ___  ____  ____     ____  ____  __    ____  ___ ");
                    Console.WriteLine(@"(  \/  )(  _  )( \/ )( ___)  (_  _) /__\  / __) / __)( ___)(  _ \   ( ___)(_  _)(  )  ( ___)/ __)");
                    Console.WriteLine(@" )    (  )(_)(  \  /  )__)     )(  /(__)\( (_-.( (_-. )__)  )(_) )   )__)  _)(_  )(__  )__) \__ \");
                    Console.WriteLine(@"(_/\/\_)(_____)  \/  (____)   (__)(__)(__)\___/ \___/(____)(____/   (__)  (____)(____)(____)(___/");
                    break;
                case 6:
                    Console.Clear();
                    Console.WriteLine("  _____      _       _     ____                 _        ");
                    Console.WriteLine(" |  __ \\    (_)     | |   |  _ \\               | |       ");
                    Console.WriteLine(" | |__) | __ _ _ __ | |_  | |_) |_ __ ___  __ _| | _____ ");
                    Console.WriteLine(" |  ___/ '__| | '_ \\| __| |  _ <| '__/ _ \\/ _` | |/ / __|");
                    Console.WriteLine(" | |   | |  | | | | | |_  | |_) | | |  __/ (_| |   <\\__ \\");
                    Console.WriteLine(" |_|   |_|  |_|_| |_|\\__| |____/|_|  \\___|\\__,_|_|\\_\\___/");
                    Console.WriteLine(" ------------------------------------------------------------- ");
                    Console.WriteLine(" | TEST                                                      |");
                    Console.WriteLine(" ------------------------------------------------------------- ");
                    break;
                case 7:
                    Console.WriteLine(@"  _____              __  __ U _____ u   _     ____    ____ U _____ u  ____     ");
                    Console.WriteLine(@" |_ - _|    ___    U|' \/ '|\| ___-|U  /-\  u|  _-\  |  _-\\| ___-|U |  _-\ u  ");
                    Console.WriteLine(@"   | |     |_-_|   \| |\/| |/|  _|-  \/ _ \//| | | |/| | | ||  _|-  \| |_) |/  ");
                    Console.WriteLine(@"  /| |\     | |     | |  | | | |___  / ___ \U| |_| |U| |_| || |___   |  _ <    ");
                    Console.WriteLine(@" u |_|U   U/| |\u   |_|  |_| |_____|/_/   \_\|____/ u|____/ |_____|  |_| \_\   ");
                    Console.WriteLine(@" _// \\.-,_|___|_,-<<,-,,-.  <<   >> \\    >> |||_    |||_  <<   >>  //   \\_  ");
                    Console.WriteLine(@"(__) (__\_)-' '-(_/ (./  \.)(__) (__(__)  (__(__)_)  (__)_)(__) (__)(__)  (__)");
                    break;
                case 9:
                    Console.WriteLine(@"    )    (               (         )       )   (     ");
                    Console.WriteLine(@" ( /(    )\ )    *   )   )\ )   ( /(    ( /(   )\ )  ");
                    Console.WriteLine(@" )\())  (()/(  ` )  /(  (()/(   )\())   )\()) (()/(  ");
                    Console.WriteLine(@"((_)\    /(_))  ( )(_))  /(_)) ((_)\   ((_)\   /(_)) ");
                    Console.WriteLine(@"  ((_)  (_))   (_(_())  (_))     ((_)   _((_) (_))   ");
                    Console.WriteLine(@" / _ \  | _ \  |_   _|  |_ _|   / _ \  | \| | / __|  ");
                    Console.WriteLine(@"| (_) | |  _/    | |     | |   | (_) | | .` | \__ \  ");
                    Console.WriteLine(@" \___/  |_|      |_|    |___|   \___/  |_|\_| |___/  ");
                    break;
                case 'n':
                    Console.WriteLine(@"Remove Normaliztion mark from filename");
                    break;
                case 'l':
                    Console.WriteLine(@"Replace string with string in filename");
                    break;
                case 'k':
                    Console.WriteLine(@"Add string to end of filename");
                    break;
                case 'm':
                    Console.WriteLine(@"Add Normaliztion mark to filename");
                    break;
                case 't':
                    Console.WriteLine(@"Remove Timestamp from filename");
                    break;
                case 'u':
                    Console.WriteLine(@"Clean Ascii filenames");
                    break;
                default:
                    
                    var version = "0.4.3";
                    Console.WriteLine(@"____   ____.__    .___            _________      .__  .__  __   ");
                    Console.WriteLine(@"\   \ /   /|__| __| _/____  ____ /   _____/_____ |  | |__|/  |_ ");
                    Console.WriteLine(@" \   Y   / |  |/ __ |/ __ \/  _ \\_____  \\____ \|  | |  \   __\");
                    Console.WriteLine(@"  \     /  |  / /_/ \  ___(  <_> )        \  |_> >  |_|  ||  |  ");
                    Console.WriteLine(@"   \___/   |__\____ |\___  >____/_______  /   __/|____/__||__|  ");
                    Console.WriteLine(@"                   \/    \/             \/|__|                  ");
                    Console.WriteLine("version: " + version.ToString());
                    Console.WriteLine("");
                    Console.WriteLine("██████████████████████████████████████████████████████████████");
                    Console.WriteLine("█                                                            █");
                    Console.WriteLine("█ Options:                                                   █");
                    Console.WriteLine("█                                                            █");
                    Console.WriteLine("█   [ 1 ] - Batch Normalize Audio   [ n ] Remove NA Mark     █");
                    Console.WriteLine("█   [ 2 ] - Print Breaks            [ u ] Remove Non-Ascii   █");
                    Console.WriteLine("█   [ 3 ] - Split Video             [ l ] Replace String     █");
                    Console.WriteLine("█   [ 4 ] - Generate Breaks                                  █");
                    Console.WriteLine("█   [ 5 ] - Move Tagged Files                                █");
                    Console.WriteLine("█   [ 6 ] - Test Print Breaks       [ k ] Add to Filename    █");
                    Console.WriteLine("█   [ 7 ] - Time Adder              [ t ] Remove Time        █");
                    Console.WriteLine("█                                                            █");
                    Console.WriteLine("█   [ 9 ] - Options                                          █");
                    Console.WriteLine("█                                                            █");
                    Console.WriteLine("██████████████████████████████████████████████████████████████");
                    break;
            }
            Console.WriteLine();
            Console.WriteLine();
        }

        static void drawMessage(string message)
        {
            Console.Clear();
            Console.WriteLine(@" _______  ___      _______  ______    _______ ");
            Console.WriteLine(@"|   _   ||   |    |       ||    _ |  |       |");
            Console.WriteLine(@"|  |_|  ||   |    |    ___||   | ||  |_     _|");
            Console.WriteLine(@"|       ||   |    |   |___ |   |_||_   |   |  ");
            Console.WriteLine(@"|       ||   |___ |    ___||    __  |  |   |  ");
            Console.WriteLine(@"|   _   ||       ||   |___ |   |  | |  |   |  ");
            Console.WriteLine(@"|__| |__||_______||_______||___|  |_|  |___|  ");
            Console.WriteLine("");
            Console.WriteLine("*****************************************************************");
            string[] lines = message.Split(new string[] { Environment.NewLine }, StringSplitOptions.None);
            foreach (string msg in lines)
            {
                Console.WriteLine(">>>>>     " + msg);
            }
            Console.WriteLine("*****************************************************************");
            Console.WriteLine("");
            Console.WriteLine("Press a key to continue!");
            Console.ReadKey();
        }

        static void recurse_add_duration(string dir)
        {
            string[] dirs = Directory.GetDirectories(dir, "*", System.IO.SearchOption.AllDirectories);

            foreach(string d in dirs)
            {
                recurse_add_duration(d);
            }

            List<string> vidlen_files = GetFiles(dir, video_file_extensions);

            while (vidlen_files.Count > 0)
            {
                AddTimeStringToFileName(vidlen_files[0]);
                vidlen_files.RemoveAt(0);
            }
            Console.WriteLine("Times have been added. Press and key to continue...");

        }

        static string ReturnCleanASCII(string s)
        {
            //taken from https://stackoverflow.com/questions/62587920/remove-unwanted-unicode-characters-from-string
            StringBuilder sb = new StringBuilder(s.Length);
            foreach (char c in s)
            {
                if ((int)c > 127) // you probably don't want 127 either
                    continue;
                if ((int)c < 32)  // I bet you don't want control characters 
                    continue;
                if (c == '?')
                    continue;
                sb.Append(c);
            }


            return sb.ToString();
        }

        static void Main(string[] args)
        {
            string app_path = AppDomain.CurrentDomain.BaseDirectory.ToString();
            if (File.Exists(app_path + "\\settings.txt"))
            {
                string[] lines = File.ReadAllLines(app_path + "\\settings.txt");
                for (int i = 0; i < lines.Count(); i++)
                {
                    string[] opt = lines[i].Split(new string[] { "=" }, StringSplitOptions.RemoveEmptyEntries);
                    switch (opt[0].ToLower().Trim())
                    {
                        case "ffmpeg location":
                            Console.WriteLine("Setting FFMPEG location to " + opt[1]);
                            ffmpeg_location = opt[1];
                            break;
                        case "alt ffmpeg location":
                            Console.WriteLine("Setting Alt FFMPEG location to " + opt[1]);
                            ffmpegX_location = opt[1];
                            break;
                        case "temp folder":
                            Console.WriteLine("Setting Temp Folder to " + opt[1]);
                            temp_folder = opt[1];
                            break;
                        case "log file":
                            Console.WriteLine("Setting Log File location to " + opt[1]);
                            Log_File = opt[1];
                            break;
                        default:

                            break;
                    }
                }
            }
            ResetLog();

            bool premature_exit = false;
            try
            {
                
                for (int i = 0; i < args.Count(); i++)
                {
                    string s = args[i];
                    if (s == "--normalize" || s == "-n") //normalize
                    {
                        //Console.Clear();
                        string f = args[i + 1];

                        if (File.Exists(f))
                        {
                            if (f.IndexOf("_NA_") > -1)
                            {
                                Console.WriteLine(f + " has already been normalized");
                            }
                            else
                            {
                                Console.WriteLine("Normalizing file: " + f);
                                premature_exit = true;
                                NormalizeAudio(f, true);
                                while(Normalizing)
                                {
                                    Application.DoEvents();
                                    Console.WriteLine("Normalizing...");
                                    System.Threading.Thread.Sleep(5000);

                                }
                                Console.WriteLine("Normaliztion ended");
                            }
                            i++;

                        }
                        else
                        {

                            Console.WriteLine(f + " doesn't exist");
                            System.Threading.Thread.Sleep(2000);
                            premature_exit = true;
                        }
                    }
                }
            }
            catch(Exception exc)
            {
                Console.WriteLine(exc.ToString());
                return;
            }
            if (premature_exit) return;
            System.Threading.Thread.Sleep(2000);
            /*
            string folder = Path.GetDirectoryName(ffmpeg_location);

            if (Directory.Exists(folder + "\\shows\\") == false) Directory.CreateDirectory(folder + "\\shows\\");
            List<string> shows = GetFiles(folder + "\\shows\\");
            //List<string> shows = GetFiles(@\shows\");
            if (Directory.Exists(folder + "\\output\\") == false) Directory.CreateDirectory(folder + "\\output\\");
            List<string> finished_shows = GetFiles(folder + "\\output\\");
            if (Directory.Exists(folder + "\\convert\\") == false) Directory.CreateDirectory(folder + "\\convert\\");
            List<string> commercials = GetFiles(folder + "\\convert\\");
            if (Directory.Exists(folder + "\\bopen\\") == false) Directory.CreateDirectory(folder + "\\bopen\\");
            List<string> bumpers_open = GetFiles(folder + "\\bopen\\");
            if (Directory.Exists(folder + "\\bclose\\") == false) Directory.CreateDirectory(folder + "\\bclose\\");
            List<string> bumpers_close = GetFiles(folder + "\\bclose\\");
            */

            while (1 == 1)
            {
                drawScreen(0); //draw the selection screen

                ConsoleKeyInfo ck = Console.ReadKey();
                char selected_option = ck.KeyChar;

                if (ck.Key == ConsoleKey.Escape) Environment.Exit(0);
                if (ffmpeg_location == "") selected_option = '9';
                switch (selected_option)
                {
                    case '1':
                        //normalize
                        drawScreen(1); //normalize

                        List<string> paths = new List<string> { };
                        bool add_paths = true;
                        string normal_audio_folder = "";
                        while (add_paths)
                        {
                            add_paths = false;
                            Console.WriteLine("Enter path to video files: (leave blank to skip)");
                            normal_audio_folder = Console.ReadLine();
                            if (normal_audio_folder == "") break;

                            if (normal_audio_folder.Substring(0, 1) == "+")
                            {
                                normal_audio_folder = normal_audio_folder.Substring(1);
                                add_paths = true;
                            }

                            if (!Directory.Exists(normal_audio_folder))
                            {
                                drawMessage("Path does not exist");
                            }
                            else
                            {
                                paths.Add(normal_audio_folder);
                                Console.WriteLine();
                                Console.WriteLine(normal_audio_folder + " added to queue (" + paths.Count.ToString() + ")");
                                Console.WriteLine();
                                Console.WriteLine();
                            }
                        }

                        TimeSpan tempTotal = TimeSpan.FromSeconds(0);

                        for (int i = 0; i < paths.Count; i++)
                        {

                            normal_audio_folder = paths[i];

                            if (normal_audio_folder != "")
                            {
                                List<string> normal_audio = GetFiles(normal_audio_folder, video_file_extensions, new string[] { "_NA_" });

                                if (normal_audio.Count > 0)
                                {
                                    int nac = 0;
                                    int nacm = normal_audio.Count;

                                    TimeSpan filter = TimeSpan.FromSeconds(0);
                                    TimeSpan apply = TimeSpan.FromSeconds(0);
                                    TimeSpan tl = TimeSpan.FromSeconds(0);

                                    while (normal_audio.Count > 0)
                                    {

                                        drawScreen(1);

                                        tempTotal = filter.Add(apply);

                                        Console.WriteLine("Estimated time remaining: " + tl.Hours.ToString().PadLeft(2, '0') + "h" + tl.Minutes.ToString().PadLeft(2, '0') + "m" + tl.Seconds.ToString().PadLeft(2, '0') + "s Elapsed: " + tempTotal.Hours.ToString().PadLeft(2, '0') + "h" + tempTotal.Minutes.ToString().PadLeft(2, '0') + "m" + tempTotal.Seconds.ToString().PadLeft(2, '0') + "s");
                                        Console.WriteLine("");

                                        Console.WriteLine("Normalizing audio for: " + Path.GetFileName(normal_audio[0]));
                                        Console.WriteLine("File " + (nac + 1).ToString() + " of " + nacm.ToString());
                                        Console.WriteLine();
                                        TimeSpan[] vals = NormalizeAudio(normal_audio[0]);

                                        filter = filter.Add(vals[0]);
                                        apply = apply.Add(vals[1]);

                                        double timeleft = ((filter.TotalSeconds + apply.TotalSeconds) / (nac + 1)) * (nacm - (nac + 1));

                                        tl = TimeSpan.FromSeconds(timeleft);



                                        if (filter.TotalSeconds == 0)
                                        {
                                            nacm--;
                                        }
                                        else
                                        {
                                            nac++;
                                        }
                                        normal_audio.RemoveAt(0);
                                    }
                                }
                            }

                        }

                        drawMessage("Normalization complete!" + Environment.NewLine + "Total time: " + tempTotal.Hours.ToString().PadLeft(2, '0') + "h" + tempTotal.Minutes.ToString().PadLeft(2, '0') + "m" + tempTotal.Seconds.ToString().PadLeft(2, '0') + "s");

                        break;
                    case '2': //print breaks
                        List<string> print_breaks = new List<string>(); // GetFiles(@"G:\Projects\Video\torrents\Murder She Wrote - Season 4");// folder + "\\print_breaks\\");

                        drawScreen(2);//print breaks

                        Console.WriteLine("Enter path to video files: (leave blank to skip)");
                        string print_folder = Console.ReadLine();
                        if (print_folder == "") break;

                        if (!Directory.Exists(print_folder))
                        {
                            drawMessage("Path does not exist");
                            break;
                        }

                        string print_breaks_folder = print_folder; // @"H:\tv station\specials\football";
                        print_breaks = GetFiles(print_breaks_folder, video_file_extensions);

                        if (print_breaks.Count > 0)
                        {


                            Console.WriteLine("");
                            Console.WriteLine("");
                            Console.WriteLine("Enter threshold: (length of BLACK frames to be considered, default 0.5 seconds)");
                            string athresh = Console.ReadLine();
                            double iathresh = 0.5;
                            try
                            {
                                if (athresh != "") iathresh = Double.Parse(athresh);
                            }
                            catch { }
                            Console.WriteLine("Threshold set to " + iathresh.ToString() + " sec(s)");

                            Console.WriteLine("");
                            Console.WriteLine("");
                            Console.WriteLine("Enter black luminance: (pure black=0, black=0.05, light black=0.1, etc, default 0.05)");
                            string ablack = Console.ReadLine();
                            double iblack = 0.10;
                            try
                            {
                                if (ablack != "") iblack = Double.Parse(ablack);
                            }
                            catch { }

                            Console.WriteLine("Black value set to " + iblack.ToString() + " sec(s)");

                            Console.WriteLine("");
                            Console.WriteLine("");
                            Console.WriteLine("Enter minimum amount of commercials (default is 3):");
                            string amincomm = Console.ReadLine();
                            double imincomm = 3;
                            try
                            {
                                if (amincomm != "") imincomm = Double.Parse(amincomm);
                            }
                            catch { }

                            Console.WriteLine("Minimum commercials set to " + imincomm.ToString() + "");

                            Console.WriteLine("");
                            Console.WriteLine("");
                            Console.WriteLine("Enter minimum start time: (time from beginning of video, default 0 seconds)");
                            string dwait = Console.ReadLine();
                            double iwait = 0;
                            try
                            {
                                if (dwait != "") iwait = Double.Parse(dwait);
                            }
                            catch { }

                            Console.WriteLine("Minimum start time set to " + iwait.ToString() + " sec(s)");

                            Console.WriteLine("");
                            Console.WriteLine("");
                            Console.WriteLine("Enter minimum length between breaks: (how long after the last break for a new break, default 300 seconds)");
                            string dblength = Console.ReadLine();
                            int iblength = 300;
                            try
                            {
                                if (dblength != "") iblength = Int32.Parse(dblength);
                            }
                            catch { }


                            Console.WriteLine("Minimum length is " + iblength.ToString() + " sec(s)");

                            Console.WriteLine("");
                            Console.WriteLine("");
                            Console.WriteLine("Enter time from end of video to stop looking for breaks: (default 0 seconds)");
                            string dend = Console.ReadLine();
                            int iend = 0;
                            try
                            {
                                if (dend != "") iend = Int32.Parse(dend);
                            }
                            catch { }


                            Console.WriteLine("End of video minus " + iend.ToString() + " sec(s)");


                            Console.WriteLine("");
                            Console.WriteLine("");
                            Console.WriteLine("Shall we make up some breaks if none are found? ([Y]es or [N]o)");


                            bool print_anyway = false;
                            string breaksmakeup = Console.ReadLine().Trim().ToLower();
                            if (breaksmakeup.Substring(0,1) == "y")
                            {
                                print_anyway = true;
                            }
                            else
                            {
                                print_anyway = false;
                            }
                            


                            while (print_breaks.Count > 0)
                            {
                                Console.WriteLine();
                                Console.WriteLine("Printing breaks for:" + print_breaks[0]);

                                string spb_output = print_breaks_folder + "\\" + Path.GetFileName(print_breaks[0]) + ".commercials";

                                if (!File.Exists(spb_output))
                                {
                                    //List<TimeSpan> cms = scanForCommercialBreaks(print_breaks[0], 0.5, iwait);
                                    List<TimeSpan> cms = NEWscanForCommercialBreaks(print_breaks[0], iathresh, iwait, iblength, iend, iblack, false);
                                    if (cms.Count >= imincomm)
                                    {
                                        Console.Write(cms.Count.ToString());
                                        //Console.ReadKey();
                                        string spb = "";
                                        foreach (TimeSpan t in cms)
                                        {
                                            AddLog("Found commercial at (in seconds): " + Math.Floor(t.TotalSeconds).ToString());
                                            //AddLog("Found commercial at (in seconds): " + (t.TotalSeconds).ToString());
                                            //spb += Math.Floor(t.TotalSeconds).ToString() + "\n";
                                            spb += t.TotalSeconds.ToString() + "\n";

                                            //spb += (t.TotalSeconds).ToString() + "\n";

                                        }
                                        if (spb != "")
                                        {
                                            File.WriteAllText(spb_output, spb.Substring(0, spb.Length - 1));
                                            AddLog("Prnted breaks: " + spb_output);
                                        }
                                    }
                                    else
                                    {
                                        Console.WriteLine("Only found " + (cms.Count).ToString() + " which is less than minimum required of " + amincomm + ". Skipping");
                                    }
                                    if (print_anyway)
                                    {
                                        Console.WriteLine("");
                                        Console.WriteLine("No breaks found, making some up!");
                                        Console.WriteLine("Getting length of video to use as a reference");
                                        string[] atime = getDurationAndAudioFilter(print_breaks[0]);
                                        Debug.WriteLine(atime[0]);
                                        if (atime[0] != "")
                                        {
                                            double ilength = (double)TimeSpan.Parse(atime[0]).TotalSeconds;
                                            if (ilength >= 0)
                                            {
                                                Console.WriteLine("Found length: " + atime[0].ToString());
                                                int ibreaks = (int)Math.Floor(ilength / 600);
                                                string sbreaks = "";
                                                for (int i = 1; i <= ibreaks; i++)
                                                {
                                                    if ((600 * i) < ilength) sbreaks += (600 * i).ToString() + "\n";
                                                }
                                                Console.WriteLine(sbreaks);
                                                if (sbreaks != "")
                                                {
                                                    File.WriteAllText(spb_output, sbreaks.Substring(0, sbreaks.Length - 1));
                                                }
                                            }
                                        }

                                    }
                                }
                                else
                                {
                                    AddLog("Commercials Already exist for this show.");
                                    //Console.Write("Commercials Already exist for this show.");
                                }
                                print_breaks.RemoveAt(0);
                            }

                            //return;
                        }
                        drawMessage("Print Breaks complete!");
                        break;
                    case '3': //split video
                        drawScreen(3); //split
                        Console.WriteLine("Enter path to video files:");
                        string split_in_folder = Console.ReadLine();

                        if (split_in_folder == "") break;

                        if (!Directory.Exists(split_in_folder))
                        {
                            drawMessage("Path does not exist");
                            break;
                        }

                        Console.WriteLine("");
                        Console.WriteLine("");

                        Console.WriteLine("Enter output path : (leave blank to create new folder \\output\\)");
                        string split_out_folder = Console.ReadLine();

                        if (split_out_folder == "")
                        {
                            split_out_folder = split_in_folder + "\\output\\";
                            if (!Directory.Exists(split_out_folder))
                            {
                                Directory.CreateDirectory(split_out_folder);
                            }
                            else
                            {
                                drawMessage("Output folder already exists" + Environment.NewLine + "Any files currently in there may be overwritten");
                            }
                        }
                        if (!Directory.Exists(split_out_folder))
                        {
                            drawMessage("Path does not exist");
                            break;
                        }

                        Console.WriteLine("");
                        Console.WriteLine("");
                        Console.WriteLine("Enter threshold: (length of time to be considered, default 0.5 seconds)");
                        string thresh = Console.ReadLine();
                        double ithresh = 0.5;
                        try
                        {
                            if (thresh != "") ithresh = Double.Parse(thresh);
                        }
                        catch { }

                        Console.WriteLine("Threshold set to " + ithresh.ToString() + " sec(s)");

                        Console.WriteLine("");
                        Console.WriteLine("");
                        Console.WriteLine("Enter black level: (0=pure black, 0.1=black, 0.2=light black, etc, default 0.05)");
                        string lblack= Console.ReadLine();
                        double ilblack = 0.05;
                        try
                        {
                            if (lblack != "") ilblack = Double.Parse(lblack);
                        }
                        catch { }

                        Console.WriteLine("Threshold set to " + ilblack.ToString() + " sec(s)");

                        Console.WriteLine("");
                        Console.WriteLine("");

                        checkSplits(split_in_folder, split_out_folder, ithresh, ilblack);

                        drawMessage("Splits have been checked!");
                        AddLog("Splits have been checked.");
                        //Console.ReadKey();
                        break;
                    case '4':
                        //print generic breaks
                        drawScreen(4);
                        //getDurationAndAudioFilter
                        Console.ReadKey();
                        break;
                    case '5':
                        //auto scan vidow for black bars

                        drawScreen(5); //bars

                        Console.WriteLine("Enter path to video files:");
                        string bbars_folder = Console.ReadLine();

                        if (bbars_folder == "") break;
                        if (!Directory.Exists(bbars_folder))
                        {
                            drawMessage("Path does not exist");
                            break;
                        }

                        Console.WriteLine();
                        Console.WriteLine();

                        MoveTaggedFiles(bbars_folder);

                        break;
                    case '6': //print breaks test
                        List<string> print_breaks_test = new List<string>(); // GetFiles(@"G:\Projects\Video\torrents\Murder She Wrote - Season 4");// folder + "\\print_breaks\\");

                        drawScreen(6);//print breaks

                        Console.WriteLine("Enter path to video file: (leave blank to skip)");
                        string print_file = Console.ReadLine();
                        if (print_file == "") break;

                        if (!File.Exists(print_file))
                        {
                            drawMessage("Path does not exist");
                            break;
                        }

                        Console.WriteLine("");
                        Console.WriteLine("");
                        Console.WriteLine("Enter threshold: (length of BLACK frames to be considered, default 0.5 seconds)");
                        string atthresh = Console.ReadLine();
                        double itathresh = 0.5;
                        try
                        {
                            if (atthresh != "") itathresh = Double.Parse(atthresh);
                        }
                        catch { }

                        Console.WriteLine("");
                        Console.WriteLine("");
                        Console.WriteLine("Enter black luminance: (pure black=0, black=0.05, light black=0.1, etc, default 0.05)");
                        string atblack = Console.ReadLine();
                        double itblack = 0.10;
                        try
                        {
                            if (atblack != "") itblack = Double.Parse(atblack);
                        }
                        catch { }

                        Console.WriteLine("Threshold set to " + itblack.ToString() + " sec(s)");

                        Console.WriteLine("");
                        Console.WriteLine("");
                        Console.WriteLine("Enter minimum start time: (time from beginning of video, default 0 seconds)");
                        string dtwait = Console.ReadLine();
                        double itwait = 0;
                        try
                        {
                            if (dtwait != "") itwait = Double.Parse(dtwait);
                        }
                        catch { }

                        Console.WriteLine("Minimum start time set to " + itwait.ToString() + " sec(s)");

                        Console.WriteLine("");
                        Console.WriteLine("");
                        Console.WriteLine("Enter minimum length between breaks: (how long after the last break for a new break, default 300 seconds)");
                        string dtlength = Console.ReadLine();
                        int itlength = 300;
                        try
                        {
                            if (dtlength != "") itlength = Int32.Parse(dtlength);
                        }
                        catch { }


                        Console.WriteLine("Minimum length is " + itlength.ToString() + " sec(s)");

                        Console.WriteLine("");
                        Console.WriteLine("");
                        Console.WriteLine("Enter time from end of video to stop looking for breaks: (default 0 seconds)");
                        string dtend = Console.ReadLine();
                        int itend = 0;
                        try
                        {
                            if (dtend != "") itend = Int32.Parse(dtend);
                        }
                        catch { }


                        Console.WriteLine("End of video minus " + itend.ToString() + " sec(s)");

                        Console.WriteLine("");
                        Console.WriteLine("Scanning for commercials...");
                        Console.WriteLine("");
                        List<TimeSpan> tcms = NEWscanForCommercialBreaks(print_file, itathresh, itwait, itlength, itend, itblack);
                        Console.WriteLine("Found " + tcms.Count.ToString() + " commercial(s)");
                        Console.WriteLine("");
                        foreach (TimeSpan t in tcms)
                        {
                            Console.WriteLine("Found commercial at (in seconds): " + t.ToString());

                        }
                        Console.WriteLine("");
                        Console.WriteLine("Settings used:");
                        Console.WriteLine("Threshhold: " + itathresh.ToString());
                        Console.WriteLine("Black Luminance: " + itblack.ToString());
                        Console.WriteLine("Minimum Start time: " + itwait.ToString());
                        Console.WriteLine("Minimum length between breaks: " + itlength.ToString());
                        Console.WriteLine("Time from end of video to stop checking: " + itend.ToString());
                        Console.WriteLine("");
                        Console.WriteLine("Press any key to return to options");
                        Console.ReadKey();
                break;
                    case '7':
                        //add video length to filename

                        drawScreen(7); //timeadder

                        Console.WriteLine("Enter path to video files:");
                        string vidlen_folder = Console.ReadLine();

                        if (vidlen_folder == "") break;
                        if (!Directory.Exists(vidlen_folder))
                        {
                            drawMessage("Path does not exist");
                            break;
                        }

                        recurse_add_duration(vidlen_folder);

                        Console.ReadKey();
                        break;

                    case '9':
                        //options
                        drawScreen(9); //options

                        Console.WriteLine("");
                        Console.WriteLine("");
                        Console.WriteLine("Enter FFMPEG Location: ");
                        if (ffmpeg_location != "") SendKeys.SendWait(ffmpeg_location);
                        string flocation = Console.ReadLine();

                        if (File.Exists(flocation) == false)
                        {
                            drawMessage("Could not verify file existance!");
                            break;
                        }

                        Console.WriteLine("");
                        Console.WriteLine("");
                        Console.WriteLine("Enter Alt FFMPEG Location: ");
                        if (ffmpeg_location != "") SendKeys.SendWait(ffmpegX_location);
                        string xlocation = Console.ReadLine();

                        if (File.Exists(xlocation) == false)
                        {
                            drawMessage("Could not verify file existance!");
                            break;
                        }

                        Console.WriteLine("");
                        Console.WriteLine("");
                        Console.WriteLine("Enter TEMP Path: (if it doesn't exist, it'll be created)");
                        if (temp_folder != "") SendKeys.SendWait(temp_folder);
                        string tlocation = Console.ReadLine();

                        if (Directory.Exists(tlocation) == false)
                        {
                            Directory.CreateDirectory(tlocation);
                        }

                        Console.WriteLine("");
                        Console.WriteLine("");
                        Console.WriteLine("Enter Log File Location: (if it doesn't exist, it'll be created)");
                        if (temp_folder != "") SendKeys.SendWait(temp_folder);
                        string llocation = Console.ReadLine();

                        if (Directory.Exists(llocation) == false)
                        {
                            Directory.CreateDirectory(llocation);
                        }

                        ffmpeg_location = flocation;
                        ffmpegX_location = xlocation;
                        temp_folder = tlocation;
                        Log_File = llocation;

                        File.WriteAllText("settings.txt", "ffmpeg location=" + ffmpeg_location + Environment.NewLine + "alt ffmpeg location=" + ffmpegX_location + Environment.NewLine + "temp folder=" + temp_folder + Environment.NewLine + "log file=" + temp_folder + Environment.NewLine);

                        break;
                    case 'n':
                        //add video length to filename

                        drawScreen('n'); //timeadder

                        Console.WriteLine("Enter path to files:");
                        string rna_folder = Console.ReadLine();

                        if (rna_folder == "") break;
                        if (Directory.Exists(rna_folder) == false)
                        {
                            drawMessage("Path does not exist: " + rna_folder);
                            break;
                        }

                        List<string> rna_files = GetFiles(rna_folder);

                        while (rna_files.Count > 0)
                        {

                            string file = rna_files[0];
                            if (File.Exists(file))
                            {
                                string path = Path.GetDirectoryName(file);
                                string file_name = Path.GetFileNameWithoutExtension(file);
                                file_name = file_name.Replace("_NA_", "");
                                string ext_name = Path.GetExtension(file);

                                Console.WriteLine();

                                File.SetAttributes(file, FileAttributes.Normal);
                                try
                                {
                                    File.Move(file, path + "\\" + file_name + ext_name);
                                }
                                catch (Exception e)
                                {
                                    Console.WriteLine(e.ToString());
                                }
                                Console.WriteLine(file + " ==> " + path + "\\" + file_name + ext_name);
                                Console.WriteLine("Removed _NA_!");
                            }

                            rna_files.RemoveAt(0);
                        }
                        Console.WriteLine("Normalization MARK (_NA_) has been removed. Press and key to continue...");

                        Console.ReadKey();
                        break;
                    case 'k':
                        //add video length to filename

                        drawScreen('k'); //timeadder

                        Console.WriteLine("Enter path to files:");
                        string kla_folder = Console.ReadLine();

                        if (kla_folder == "") break;
                        if (Directory.Exists(kla_folder) == false)
                        {
                            drawMessage("Path does not exist: " + kla_folder);
                            break;
                        }

                        List<string> kla_files = GetFiles(kla_folder);

                        Console.WriteLine("Enter string to append to file:");
                        string kda_string = Console.ReadLine();
                        if (kda_string != "")
                        {

                            while (kla_files.Count > 0)
                            {

                                string file = kla_files[0];
                                if (File.Exists(file) && file.IndexOf(kda_string) < 0)
                                {
                                    string path = Path.GetDirectoryName(file);
                                    string file_name = Path.GetFileNameWithoutExtension(file);
                                    string ext_name = Path.GetExtension(file);

                                    Console.WriteLine();

                                    File.SetAttributes(file, FileAttributes.Normal);
                                    try
                                    {
                                        File.Move(file, path + "\\" + file_name + kda_string + ext_name);
                                    }
                                    catch (Exception e)
                                    {
                                        Console.WriteLine(e.ToString());
                                    }
                                    Console.WriteLine(file + " ==> " + path + "\\" + file_name + kda_string + ext_name);
                                    Console.WriteLine("Done!");
                                }

                                kla_files.RemoveAt(0);
                            }
                            Console.WriteLine(kda_string + " has been added. Press and key to continue...");

                            Console.ReadKey();
                        }

                        Console.WriteLine("Enter string to prepend to file:");
                        kda_string = Console.ReadLine();
                        if (kda_string != "")
                        {

                            while (kla_files.Count > 0)
                            {

                                string file = kla_files[0];
                                if (File.Exists(file) && file.IndexOf(kda_string) < 0)
                                {
                                    string path = Path.GetDirectoryName(file);
                                    string file_name = Path.GetFileNameWithoutExtension(file);
                                    string ext_name = Path.GetExtension(file);

                                    Console.WriteLine();

                                    File.SetAttributes(file, FileAttributes.Normal);
                                    try
                                    {
                                        File.Move(file, path + "\\" + kda_string + file_name  + ext_name);
                                    }
                                    catch (Exception e)
                                    {
                                        Console.WriteLine(e.ToString());
                                    }
                                    Console.WriteLine(file + " ==> " + path + "\\" + kda_string + file_name + ext_name);
                                    Console.WriteLine("Done!");
                                }

                                kla_files.RemoveAt(0);
                            }
                            Console.WriteLine(kda_string + " has been added. Press and key to continue...");

                            Console.ReadKey();
                        }

                        break;
                    case 'l':
                        //add video length to filename

                        drawScreen('l'); //timeadder

                        Console.WriteLine("Enter path to files:");
                        string rla_folder = Console.ReadLine();

                        if (rla_folder == "") break;
                        if (Directory.Exists(rla_folder) == false)
                        {
                            drawMessage("Path does not exist: " + rla_folder);
                            break;
                        }

                        List<string> rla_files = GetFiles(rla_folder);

                        Console.WriteLine("Enter string to find:");
                        string da_string = Console.ReadLine();
                        if (da_string == "") break;

                        Console.WriteLine("Enter string to replace:");
                        string rep_string = Console.ReadLine();
                        

                        while (rla_files.Count > 0)
                        {

                            string file = rla_files[0];
                            if (File.Exists(file))
                            {
                                string path = Path.GetDirectoryName(file);
                                string file_name = Path.GetFileNameWithoutExtension(file);
                                file_name = file_name.Replace("_NA_", "|||||");
                                if (file_name.IndexOf(da_string) > -1)
                                {
                                    file_name = file_name.Replace(da_string, rep_string);

                                    file_name = file_name.Replace("|||||", "_NA_");
                                    string ext_name = Path.GetExtension(file);

                                    Console.WriteLine();

                                    File.SetAttributes(file, FileAttributes.Normal);
                                    try
                                    {
                                        File.Move(file, path + "\\" + file_name + ext_name);
                                    }
                                    catch (Exception e)
                                    {
                                        Console.WriteLine(e.ToString());
                                    }
                                    Console.WriteLine(file + " ==> " + path + "\\" + file_name + ext_name);
                                    Console.WriteLine("Done!");
                                }
                            }

                            rla_files.RemoveAt(0);
                        }
                        Console.WriteLine(da_string + " has been replaced with " + rep_string + ". Press and key to continue...");

                        Console.ReadKey();
                        break;
                    case 'm':
                        //add normalized audio mark to file name (_NA_)

                        drawScreen('m'); //

                        Console.WriteLine("Enter path to files:");
                        string mrna_folder = Console.ReadLine();

                        if (mrna_folder == "") break;
                        if (Directory.Exists(mrna_folder) == false)
                        {
                            drawMessage("Path does not exist: " + mrna_folder);
                            break;
                        }

                        List<string> mrna_files = GetFiles(mrna_folder);

                        while (mrna_files.Count > 0)
                        {

                            string file = mrna_files[0];
                            if (File.Exists(file))
                            {
                                string path = Path.GetDirectoryName(file);
                                string file_name = Path.GetFileNameWithoutExtension(file);
                                string ext_name = Path.GetExtension(file);
                                string nfile = "";
                                if (file_name.IndexOf("_NA_") == -1)
                                {

                                    Console.WriteLine();

                                    File.SetAttributes(file, FileAttributes.Normal);

                                    try
                                    {

                                        if (ext_name.ToLower() == ".commercials")
                                        {
                                            ext_name = Path.GetExtension(path + "\\" + file_name);
                                            file_name = Path.GetFileNameWithoutExtension(path + "\\" + file_name);
                                            nfile = path + "\\" + file_name + "_NA_" + ext_name + ".commercials";
                                            File.Move(file, nfile);
                                        }
                                        else
                                        {
                                            nfile = path + "\\" + file_name + "_NA_" + ext_name;
                                            File.Move(file, nfile);
                                        }
                                    }
                                    catch (Exception e)
                                    {
                                        Console.WriteLine(e.ToString());
                                    }
                                    Console.WriteLine(file + " ==> " + nfile);
                                    Console.WriteLine("Added _NA_!");
                                }
                            }

                            mrna_files.RemoveAt(0);
                        }
                        Console.WriteLine("Normalization MARK (_NA_) has been ADDED. Press and key to continue...");

                        Console.ReadKey();
                        break;
                    case 'u':
                        //add video length to filename

                        drawScreen('u'); //timeadder

                        Console.WriteLine("Enter path to files:");
                        string urna_folder = Console.ReadLine();

                        if (urna_folder == "") break;
                        if (Directory.Exists(urna_folder) == false)
                        {
                            drawMessage("Path does not exist: " + urna_folder);
                            break;
                        }

                        List<string> urna_files = GetFiles(urna_folder);

                        while (urna_files.Count > 0)
                        {

                            string file = urna_files[0];
                            if (File.Exists(file))
                            {
                                string path = Path.GetDirectoryName(file);
                                string file_name = Path.GetFileNameWithoutExtension(file);
                                file_name = ReturnCleanASCII(file_name);
                                string ext_name = Path.GetExtension(file);

                                Console.WriteLine();

                                File.SetAttributes(file, FileAttributes.Normal);
                                try
                                {
                                    File.Move(file, path + "\\" + file_name + ext_name);
                                }
                                catch (Exception e)
                                {
                                    Console.WriteLine(e.ToString());
                                }
                                Console.WriteLine(file + " ==> " + path + "\\" + file_name + ext_name);
                                Console.WriteLine("Clean Ascii Filenames complete!");
                            }

                            urna_files.RemoveAt(0);
                        }
                        Console.WriteLine("Clean Ascii Filenames complete! Press and key to continue...");

                        Console.ReadKey();
                        break;
                    case 't':
                        //add video length to filename

                        drawScreen('t'); //timeadder

                        Console.WriteLine("Enter path to files:");
                        string tna_folder = Console.ReadLine();

                        if (tna_folder == "") break;
                        if (Directory.Exists(tna_folder) == false)
                        {
                            drawMessage("Path does not exist: " + tna_folder);
                            break;
                        }

                        List<string> tna_files = GetFiles(tna_folder);

                        while (tna_files.Count > 0)
                        {

                            string file = tna_files[0];
                            if (File.Exists(file))
                            {
                                string path = Path.GetDirectoryName(file);
                                string file_name = Path.GetFileNameWithoutExtension(file);
                                string ext_name = Path.GetExtension(file);
                                int itna = file_name.IndexOf("%T(");
                                int itnb = file_name.IndexOf(")%", itna+1);
                                if (itna > -1 && itnb > -1)
                                {

                                    file_name = file_name.Substring(0, itna) + file_name.Substring(itnb+2);

                                    Console.WriteLine();

                                    File.SetAttributes(file, FileAttributes.Normal);
                                    try
                                    {
                                        File.Move(file, path + "\\" + file_name + ext_name);
                                    }
                                    catch (Exception e)
                                    {
                                        Console.WriteLine(e.ToString());
                                    }
                                    Console.WriteLine(file + " ==> " + path + "\\" + file_name + ext_name);
                                    Console.WriteLine("Removed %(timestamp)%!");
                                }
                            }

                            tna_files.RemoveAt(0);
                        }
                        Console.WriteLine("Timestamp %(timestamp)% has been removed. Press and key to continue...");

                        Console.ReadKey();
                        break;
                    default:
                        Console.CursorVisible = false;
                        drawMessage("Invalid Entry!");
                        break;
                }
            }
        }
    }
}