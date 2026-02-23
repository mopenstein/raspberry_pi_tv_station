# File Naming & Preparation
To ensure the TV Station accurately injects commercials, all video files must follow these preparation steps:

1. Audio Normalization
All video files should have their audio levels normalized for a consistent broadcast experience.

Files in this directory are marked with _NA_ to indicate they have been volume-corrected.

2. Duration Tagging
Videos must be tagged in the filename with their total length in seconds (rounded up).

Format: %T(seconds)%
Example (Cartoon): Popeye_AHaulInOne_%T(365)%_NA_.mp4 (365 seconds)
Example (Ad): Gillette_1955_%T(30)%_NA_.mp4 (30 seconds)

3. Automation
These preparation steps (normalization and tagging) can be automated using the C# utility VideoSplit. https://github.com/mopenstein/VideoSplit

# Media Examples & Public Domain Status

The video files in this directory are provided as examples. They also illustrate the naming conventions for injecting commercials into a TV Station broadcast.

Popeye: A Haul in One (1956): This work is in the public domain in the United States due to a failure to renew copyright (originally registered by Paramount/Famous Studios).

Gillette Commercial (1955): This work is presumed to be in the public domain due to the absence of a mandatory copyright notice at the time of publication and/or lack of renewal.

These files are provided for demonstration and example purposes only. All trademarks (e.g., Gillette, Popeye) remain the property of their respective owners; their inclusion here is nominative and intended solely for historical context.
