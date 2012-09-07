#!/usr/bin/env python

# Copyright (C) 2012 Ulteo SAS
# http://www.ulteo.com
# Author David PHAM-VAN <d.pham-van@ulteo.com> 2012
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; version 2
# of the License
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

import os
import re
import tempfile
from subprocess import Popen, PIPE
import Image

def jscompress(outfile, filename):
	print " * compress %s" % filename
	output = Popen(("yui-compressor", "--type", "js", "--charset", "utf-8", filename), stdout=PIPE).stdout.read()
	outfile.write("\n\n/* %s */\n" % filename)
	outfile.write(output)


def csscompress(outfile, filename):
	print " * compress %s" % filename
	output = Popen(("yui-compressor", "--type", "css", "--charset", "utf-8", filename), stdout=PIPE).stdout.read()
	outfile.write("\n\n/* %s */\n" % filename)
	outfile.write(output)


def listImages(apath):
	for root, dirs, files in os.walk(apath):
		for curfile in files:
			if (curfile.endswith('.png') or curfile.endswith('.jpeg') or curfile.endswith('.gif')) and not curfile in ('favicon.png', 'uovd.png'):
				yield os.path.join(root, curfile)


def make_image_map(images, width=None):
	cimages = [image for image in images if not os.path.basename(image['fname']) in ('rotate.gif', 'loader.gif')]
	
	cimages.sort(lambda x,y:-1*cmp(x['width'], y['width']))
	if width == None:
		width = max(cimages[0]['width'], 300)
	
	margin=1
	cw = 0
	ch = 0
	height = 0
	while len(cimages) > 0:
		found = False
		for image in cimages:
			if cw + image['width'] <= width:
				image['uovd'] = True
				image['posx'] = -1*cw
				image['posy'] = -1*ch
				image['fname'] = '../image/uovd.png'
				height = max(height, image['height'])
				cw += image['width'] + margin
				cimages.remove(image)
				found = True
				break
		
		if not found or len(cimages) == 0:
			cw = 0
			ch += height + margin
			height = 0
	
	output = Image.new("RGBA", (width, ch))
	
	for image in images:
		if image.get('uovd', False):
			output.paste(image['image'], (-image['posx'], -image['posy']))
	
	return output


def compile_images():
	startpath=os.path.join("web", "media", "")
	images = []
	for fname in listImages(os.path.join("web", "media", "image")):
		css = {}
		css["fname"] = os.path.join(os.path.pardir, fname.replace(startpath, ''))
		css["class"] = os.path.basename(fname).replace('.', '_')
		css["posx"] = 0
		css["posy"] = 0
		css["image"] = Image.open(fname)
		css["width"] = css["image"].size[0]
		css["height"] = css["image"].size[1]
		images.append(css)
	
	imagemap = make_image_map(images)
	uovd_png = tempfile.NamedTemporaryFile()
	imagemap.save(uovd_png, 'PNG')
	print Popen(("pngcrush", uovd_png.name, os.path.join("web", "media", "image", "uovd.png")), stdout=PIPE).stdout.read()
	
	cssimages = os.path.join("web", "media", "style", "images.css")
	cssimagesfile = open(cssimages, "w")
	for css in images:
		cssimagesfile.write(""".image_%(class)s {
  background: url(\"%(fname)s\") no-repeat scroll %(posx)dpx %(posy)dpx transparent;
  width: %(width)dpx;
  height: %(height)dpx;
}

""" % css)
	cssimagesfile.close()


copyright = """/**
* Copyright (C) 2012 Ulteo SAS
* http://www.ulteo.com
* this is the minified version of multiple files
* see the original files for individual copyright information
**/"""

if __name__ == "__main__":
	compile_images()

	f = open(os.path.join("web", "index.php"))
	content = f.read()
	f.close()

	outfilename = os.path.join("web", "media", "script", "uovd.js")
	outfile = open(outfilename, "w")
	outfile.write(copyright)

	for match in re.findall("<script type=\"text/javascript\" src=\"media/(.*).js[^\"]*\" charset=\"utf-8\"></script>", content, re.IGNORECASE):
		filename = os.path.join("web", "media", match + ".js")
		if not os.path.basename(filename) in ("uovd.js", "uovd_int_client.js", "uovd_ext_client.js"):
			jscompress(outfile, filename)

	outfile.close()

	outfilename = os.path.join("web", "media", "style", "uovd.css")
	outfile = open(outfilename, "w")
	outfile.write(copyright)

	for match in re.findall("<link rel=\"stylesheet\" type=\"text/css\" href=\"media/(.*).css\" />", content, re.IGNORECASE):
		filename = os.path.join("web", "media", match + ".css")
		if not os.path.basename(filename) in ("uovd.css", ):
			csscompress(outfile, filename)

	outfile.close()