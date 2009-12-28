#!/usr/bin/python
# -*- coding: UTF-8 -*-

# Copyright (C) 2008,2009 Ulteo SAS
# http://www.ulteo.com
# Author Laurent CLOUET <laurent@ulteo.com> 2008,2009
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

from Logger import Logger
from Session import Session

from SimpleHTTPServer import SimpleHTTPRequestHandler
import cgi
import os
import sys
import base64
import httplib
from xml.dom.minidom import Document
import utils
import pythoncom
import tempfile
import servicemanager
from win32com.shell import shell
import time
import traceback
import win32api
import win32con

class Web(SimpleHTTPRequestHandler):
	def do_GET(self):
		root_dir = '/applicationserver'
		try:
			if self.server.daemon.isSessionManagerRequest(self.client_address[0]) == False:
				self.send_error(httplib.UNAUTHORIZED)
				return
			
			Logger.debug("do_GET "+self.path)
			if self.path == root_dir+"/webservices/server_status.php":
				self.webservices_server_status()
			elif self.path == root_dir+"/webservices/server_monitoring.php":
				self.webservices_server_monitoring()
			elif self.path == root_dir+"/webservices/server_type.php":
				self.webservices_server_type()
			elif self.path == root_dir+"/webservices/server_version.php":
				self.webservices_server_version()
			elif self.path == root_dir+"/webservices/applications.php":
				self.webservices_applications()
			elif self.path.startswith(root_dir+"/webservices/icon.php"):
				self.webservices_icon()
			elif self.path.startswith(root_dir+"/webservices/server_log.php"):
				self.webservices_server_log()
			else:
				self.send_error(httplib.NOT_FOUND)
				return
			
		except Exception, err:
			exception_type, exception_string, tb = sys.exc_info()
			trace_exc = "".join(traceback.format_tb(tb))
			Logger.debug("do_GET error '%s' '%s'"%(trace_exc, str(exception_string)))

	def do_POST(self):
		root_dir = '/applicationserver/webservices'
		try:
			if self.server.daemon.isSessionManagerRequest(self.client_address[0]) == False:
				self.send_error(httplib.UNAUTHORIZED)
				return

			if self.path.startswith(root_dir+"/session"):
				self.webservices_session(self.path[len(root_dir+"/session"):])
				
			elif self.path == root_dir+"/domain":
				self.webservices_domain()
			
			else:
				self.send_error(httplib.NOT_FOUND)
				return
			
		except Exception, err:
			Logger.error("exception: "+str(err))


	@staticmethod
	def error2xml(code, message=None):
		doc = Document()
		
		rootNode = doc.createElement("error")
		rootNode.setAttribute("id", code)
		
		if message is not None:
			textNode = doc.createTextNode(message)
			rootNode.appendChild(textNode)
		
		doc.appendChild(rootNode)
		return doc


	def webservices_session_answer(self, content):
		self.send_response(httplib.OK)
		self.send_header('Content-Type', 'text/xml')
		self.end_headers()
		self.wfile.write(content.toprettyxml())
		return

	def webservices_session(self, request):
		Logger.debug("Communication do_POST webservice session (%s)"%(request))
		internalSM = self.server.daemon.internalSM
		
		if request == "/create":
			try:
				length = int(self.headers["Content-Length"])
				content = self.rfile.read(length)
				s = Session(content)
			
			except Exception, e:
				return self.webservices_session_answer(self.error2xml("usage", str(e)))
			
			if internalSM.exist(s.id):
				return self.webservices_session_answer(self.error2xml("already exist session"))
			
			try:
				s.init()
	
			except Exception, e:
				return self.webservices_session_answer(self.error2xml("user", str(e)))
			
			internalSM.add(s)

			doc = Document()
			rootNode = doc.createElement("session")
			rootNode.setAttribute("id", s.id)
			if s.userParams[0] != s.user.login:
				rootNode.setAttribute("login", s.user.login)
			doc.appendChild(rootNode)

			return self.webservices_session_answer(doc)


		elif request.startswith("/destroy/"):
			session_id = request[len("/destroy/"):]
			if not internalSM.exist(session_id):
				return self.webservices_session_answer(self.error2xml("unknwown"))

			s = internalSM.get(session_id)
			if s.getState() in [Session.DESTROYING, Session.DESTROYED]:
				return self.webservices_session_answer(self.error2xml("already"))
			
			s.orderDestroy()
		
			doc = Document()
			rootNode = doc.createElement("session")
			rootNode.setAttribute("id", s.id)
			doc.appendChild(rootNode)
		
			return self.webservices_session_answer(doc)

	
		elif request.startswith("/status/"):
			session_id = request[len("/status/"):]
			if not internalSM.exist(session_id):
				return self.webservices_session_answer(self.error2xml("unknwown"))

			s = internalSM.get(session_id)
			state = s.getState()
			if state == Session.DESTROYED:
				internalSM.delete(s)
				# todo: find a better way to do that

			doc = Document()
			rootNode = doc.createElement("session")
			rootNode.setAttribute("id", s.id)
			rootNode.setAttribute("satus", str(state))
			doc.appendChild(rootNode)
			return self.webservices_session_answer(doc)
	
	def log_request(self,l):
		pass
	
	def webservices_server_status(self):
		self.send_response(httplib.OK)
		self.send_header('Content-Type', 'text/plain')
		self.end_headers()
		self.wfile.write(self.server.daemon.getStatusString())
	
	def webservices_server_monitoring(self):
		doc = self.server.daemon.xmlMonitoring()
		
		self.send_response(httplib.OK)
		self.send_header('Content-Type', 'text/xml')
		self.end_headers()
		self.wfile.write(doc.toxml())
	
	def webservices_server_type(self):
		self.send_response(httplib.OK)
		self.send_header('Content-Type', 'text/plain')
		self.end_headers()
		self.wfile.write('windows')
	
	def webservices_server_version(self):
		self.send_response(httplib.OK)
		self.send_header('Content-Type', 'text/plain')
		self.end_headers()
		self.wfile.write(self.server.daemon.version_os)
	
	def webservices_applications(self):
		self.send_response(httplib.OK)
		self.send_header('Content-Type', 'text/xml')
		self.end_headers()
		self.wfile.write(self.server.daemon.getApplicationsXML())
	
	def webservices_icon(self):
		try :
			args = {}
			args2 = cgi.parse_qsl(self.path[self.path.index('?')+1:])
			for (k,v) in args2:
				args[k] = base64.decodestring(v).decode('utf-8')
		except Exception, err:
			args = {}
		
		if args.has_key('desktopfile'):
			if args['desktopfile'] != '':
				if os.path.exists(args['desktopfile']):
					pythoncom.CoInitialize()
					shortcut = pythoncom.CoCreateInstance(shell.CLSID_ShellLink, None, pythoncom.CLSCTX_INPROC_SERVER, shell.IID_IShellLink)
					shortcut.QueryInterface( pythoncom.IID_IPersistFile ).Load(args['desktopfile'])
					if (os.path.splitext(shortcut.GetPath(0)[0])[1].lower() == ".exe"):
						exe_file = shortcut.GetPath(0)[0]
						path_bmp = tempfile.mktemp()+'.bmp'
						
						command = """"%s" "%s" "%s" """%(os.path.join(self.server.daemon.install_dir, 'extract_icon.exe'), exe_file, path_bmp)
						p = utils.Process()
						status = p.run(command)
						Logger.debug("status of extract_icon %s (command %s)"%(status, command))
						
						if os.path.exists(path_bmp):
							path_png = tempfile.mktemp()+'.png'
							
							command = """"%s" -Q -O "%s" "%s" """%(os.path.join(self.server.daemon.install_dir, 'bmp2png.exe'), path_png, path_bmp)
							p = utils.Process()
							status = p.run(command)
							Logger.debug("status of bmp2png %s (command %s)"%(status, command))
							
							f = open(path_png, 'rb')
							self.send_response(httplib.OK)
							self.send_header('Content-Type', 'image/png')
							self.end_headers()
							self.wfile.write(f.read())
							f.close()
							os.remove(path_bmp)
							os.remove(path_png)
						else :
							Logger.debug("webservices_icon error 500")
							self.send_error(httplib.INTERNAL_SERVER_ERROR)
					else :
						Logger.debug("webservices_icon send default icon")
						f = open('icon.png', 'rb')
						self.send_response(httplib.OK)
						self.send_header('Content-Type', 'image/png')
						self.end_headers()
						self.wfile.write(f.read())
						f.close()
				else:
					Logger.debug("webservices_icon no right argument1")
			else:
				Logger.debug("webservices_icon no right argument2" )
		else:
			Logger.debug("webservices_icon no right argument3" )
	
	def webservices_server_log(self):
		try :
			args = {}
			args2 = cgi.parse_qsl(self.path[self.path.index('?')+1:])
			for (k,v) in args2:
				args[k] = v.decode('utf-8')
		except Exception, err:
			Logger.debug("webservices_server_log error decoding args %s"%(err))
			args = {}
		
		Logger.debug("webservices_server_log args: "+str(args))
		
		if not args.has_key('since'):
			Logger.warn("webservices_server_log: no since arg")
			self.send_error(httplib.BAD_REQUEST)
			return
		
		try:
			since = int(args['since'])
		except:
			Logger.warn("webservices_server_log: since arg not int")
			self.send_error(httplib.BAD_REQUEST)
			return

		(last, data) = self.getLogSince(since)
		data = base64.encodestring("".join(data))
		
		doc = Document()
		rootNode = doc.createElement("log")
		rootNode.setAttribute("since", str(since))
		rootNode.setAttribute("last", str(int(last)))

		node = doc.createElement("web")
		dataNode = doc.createCDATASection(data)
		node.appendChild(dataNode)
		rootNode.appendChild(node)
		
		node = doc.createElement("daemon")
		dataNode = doc.createCDATASection("")
		node.appendChild(dataNode)
		rootNode.appendChild(node)
		
		doc.appendChild(rootNode)
		
		self.send_response(httplib.OK)
		self.send_header('Content-Type', 'text/xml')
		self.end_headers()
		self.wfile.write(doc.toxml())
		
		return
	
	@staticmethod
	def getTimeFromLine(line):
		try:
			d,_ = line.split(",", 1)		
			t = time.strptime(d, "%Y-%m-%d %H:%M:%S")
		except:
			return False
		
		return time.mktime(t)
	
	def getLogSince(self, since):
		if not os.path.isfile(self.server.daemon.conf["log_file"]):
			return (since, [])
		
		f = open(self.server.daemon.conf["log_file"], 'rb')
		lines = f.readlines()
		i = len(lines) - 1
		
		data = []
		last = None
		data_buffer = []
		
		while(i > 0):
			line = lines[i]
			i-= 1
			t = self.getTimeFromLine(line)
			
			if t is False:
				data_buffer.append(line)
				continue
			
			if last is None:
				last = t
			
			if t < since:
				break
			
			if t >= last:
				utils.array_flush(data_buffer)
				continue
			
			for elem in utils.array_flush(data_buffer):
				data.append(elem)
			data.append(line)
		
		if last is None:
			data = data_buffer
			last = since
		
		data.reverse()
		return (last, data)
	
	
	def webservices_domain(self):
		Logger.info("webservices_domain")
		doc = Document()
		domain = None
		
		try:
			domain = win32api.GetComputerNameEx(win32con.ComputerNameDnsDomain)
		except Excpetion, e:
			Logger.warn("webservices_domain: exception '%s'"%(str(e)))
			rootNode = doc.createElement("error")
			rootNode.setAttribute("id", "internal")
			
			textNode = doc.createTextNode(str(e))
			rootNode.appendChild(textNode)
		
		if domain is not None:
			if domain == u"":
				Logger.info("webservices_domain: no domain")
				rootNode = doc.createElement("error")
				rootNode.setAttribute("id", "no_domain")
			else:
				rootNode = doc.createElement("domain")
				rootNode.setAttribute("name", domain)
				
				Logger.info("webservices_domain: '%s'"%(domain))
		
		doc.appendChild(rootNode)
		self.send_response(httplib.OK)
		self.send_header('Content-Type', 'text/xml')
		self.end_headers()
		self.wfile.write(doc.toxml())
		return
