Knoxious Open Pastebin
======================
### v1.6.0 

Copyright (c) 2009-2011 Xan Manning (http://xan-manning.co.uk/)

Released under the terms of the MIT License.
See the MIT for details (http://opensource.org/licenses/mit-license.php).

A quick to set up, rapid install, two-file pastebin! (or at least can be) Supports text and image hosting, url and video linking.

 * URL: http://xan-manning.co.uk/
 * EXAMPLE: http://pzt.me/


[![Flattr this git repo](http://api.flattr.com/button/flattr-badge-large.png)](https://flattr.com/submit/auto?user_id=xan.manning&url=https://github.com/xanmanning/Knoxious-Open-Pastebin&title=Knoxious-Open-Pastebin&language=&tags=github&category=software) 


CONTENTS
--------

1. Authors / Contributors
2. Features
3. Requirements
4. Installation
5. ToDo
6. Copying


FEATURES
--------

Here is a list of features that this pastebin software boasts.

 * Line Highlighting
 * Syntax Highlighting (with GeSHi, not included)
 * Editing pastes, history.
 * Copy to clipboard (with _clipboard.swf, included - requires swfobject.js, not included.)
 * Image hosting
 * Copying images from URLs
 * Embedding videos from YouTube, DailyMotion and Vimeo
 * Flash player for flv and mp4 files (with FlowPlayer, not included)
 * URL Shortening/redirection
 * Visual Effects (with jQuery, not included)
 * AJAX Posting (with jQuery, not included)
 * Developer API access.
 * Custom Subdomains
 * Robot Spam protection
 * Paste privacy settings
 * Paste lifespan settings
 * Password Protected/Encrypted Pastes
 * List pastes by Username


AUTHORS/CONTRIBUTORS
--------------------

 * Xan Manning (xan[dot]manning[at]gmail[dot]com)
 * Matt Klich https://github.com/elementalvoid
 * Shadow-Majestic https://github.com/shadowmajestic
 * Plytro https://github.com/plytro
 * Moritz Naumann http://moritz-naumann.com



REQUIREMENTS
------------

A number of systems have been tested in the production of this Pastebin, some setups are better than others but we have two grades of setup below.


### Recommended

Linux*

 * Apache2
 * PHP 5.2+
 * MySQL (Optional)
 * Git or Mercurial (Optional)


### Confirmed to work

Linux*

 * Lighttpd + FastCGI
 * nginx (>= 0.7.x) + FastCGI

Windows Server 2008 R2

 * IIS 7.5 + FastCGI
 * Apache2

Windows (server) 8

 * IIS 8.0 + FastCGI
 * Apache2
	
### Pending Investigation
If it works on Linux it will likely run on the Windows/Mac/FreeBSD release.

Linux*

 * cherokee
 * litespeed

Windows Server 2003

 * IIS 6

Windows Server 2008

 * IIS 7

FreeBSD

Mac OS X


### Linux OS tested were, will undoubtably run on all others:

 * Debian (Lenny and Squeeze)
 * Ubuntu (9.10, 10.04)
 * CentOS 5
 * Fedora Core 11, 12	



INSTALLATION
------------

1. Copy config.php.dist to config.php
2. Change the $CONFIG variables in config.php
3. Download any of the required 'wares (eg, GeSHi, _clipboard.swf, jQuery, etc.)
4. Upload to your server
5. CHMOD the containing folder to 0777.
6. Visit http://yoursite/path/to/pastebin/index.php
7. Watch it install.
8. CHMOD the containing folder to 0755.
9. Done.


TODO
----

 * Code Tidy, Optimise, remove bloat.
 * Control ID format (Numerical, Alphanumerical, "Random" and Hex)
 * Password Protected and Encrypted Pastes (CURRENTLY IN TESTING BRANCH, NEEDS FIX)
 * Encryption between browser and server for Encrypted Pastes
 * List Author Pastes, details of paste. (CURRENTLY IN UNSTABLE BRANCH, UNFORMATTED)
 * Image Thumbnails (Using either GD or ImageMagick)
 * Languages
 * Download link next to Raw, extension when download (Thanks Schwarzenbart and Zac)
 * DIFF between parent and child.
 * Fix HTML5 Compatability.
 * Using CouchDB (Maybe?)
 * Remove Video stuff... - Awkward to maintain.
 * Change the way that Syntax Highlighting stuff is stored (Save disk space)
 * Temporary user paste deletion rights
 * IP Bans
 * XML or JSON API Response


COPYING
-------

Copyright (c) 2009-2011 Xan Manning (http://xan-manning.co.uk)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
