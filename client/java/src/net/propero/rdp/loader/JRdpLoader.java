/* JRdpLoader.java
 * Component: ProperJavaRDP
 * 
 * Revision: $Revision: 1.1.1.1 $
 * Author: $Author: suvarov $
 * Date: $Date: 2007/03/08 00:26:42 $
 *
 * Copyright (c) 2005 Propero Limited
 *
 * Purpose: Launch ProperJavaRDP with settings from a config file
 */
package net.propero.rdp.loader;

import java.io.BufferedReader;
import java.io.FileInputStream;
import java.io.IOException;
import java.io.InputStreamReader;
import java.util.StringTokenizer;

import net.propero.rdp.OrderException;
import net.propero.rdp.Rdesktop;
import net.propero.rdp.RdesktopException;
import net.propero.rdp.Utilities_Localised;

public class JRdpLoader {

	// Set of identifiers to be found within the launch file
	private static String[] identifiers = {
			"--user", 
			"--password",
			"--domain",
			"--fullscreen",
			"--geometry",
			"--use_rdp5"
	};
	
	// Set of command-line options mapping to the launch file identifiers
	private static String[] pairs = {
			"-u",
			"-p",
			"-d",
			"-f",
			"-g",
			"--use_rdp5"
	};
	
	public static void main(String args[]){
		
		if(args.length <= 0){
			System.err.println("Expected usage: JRdpLoader launchFile");
			System.exit(-1);
		}
		
		String launchFile = args[0];
	
		String server = ""; String port = "";
		
		try{
			String outArgs = "";
			
			// Open the file specified at the command-line
			FileInputStream fstream = new FileInputStream(launchFile);
			BufferedReader in = new BufferedReader(new InputStreamReader(fstream));
			String line = null;
			while ((line = in.readLine()) != null) {
				StringTokenizer stok = new StringTokenizer(line);
				if(stok.hasMoreTokens()){
					String identifier = stok.nextToken();
					String value = "";
					while(stok.hasMoreTokens()){
						value += stok.nextToken();
						if(stok.hasMoreTokens()) value += " ";
					}
				
					if(identifier.equals("--server")) server = value;
					else if(identifier.equals("--port")) port = value;
					else{	
						String p = getParam(identifier);
						if(p != null) outArgs += p + " " + value + " ";
					}
				}
			}
			
			if(server != null && server != ""){
				outArgs += server;
				if(port != null && port != "") outArgs += ":" + port;
				
                //String[] finArgs = outArgs.split(" ");
                String[] finArgs = Utilities_Localised.split(outArgs, " ");
                
                Rdesktop.main(finArgs);
				in.close();
			}else{ System.err.println("No server name provided"); System.exit(0); }
			
        
		}
		catch(IOException ioe){ System.err.println("Launch file could not be read: " + ioe.getMessage()); System.exit(-1); } 
		catch (OrderException e) { e.printStackTrace(); System.exit(-1); } 
		catch (RdesktopException e) { e.printStackTrace(); System.exit(-1);	}
		catch(Exception e){ e.printStackTrace(); System.exit(-1); }
	}
	
	private static String getParam(String identifier){
		for(int i = 0; i < identifiers.length; i++){
			if(identifier.equals(identifiers[i])){
				return pairs[i];
			}
		}
		
		return null;
	}
	
}
