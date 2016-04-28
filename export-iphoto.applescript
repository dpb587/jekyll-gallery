on run argv
	tell application "Photos"
		set vAlbum to first item of (get every album whose name is (item 1 of argv))
		set vPhotos to get every media item in vAlbum
		
		set output to ""
		
		repeat with vPhoto in vPhotos
			set loc to the location of vPhoto
			set lati to (the first item of loc) as string
			set longi to (the second item of loc) as string
			set output to (output & Â
				"altitude: " & altitude of vPhoto & "
" & Â
				"longitude: " & longi & "
" & Â
				"latitude: " & lati & "
" & Â
				"name: " & filename of vPhoto & "
" & Â
				"date: " & date of vPhoto & "
" & Â
				"title: " & filename of vPhoto & "
" & Â
				"path: /Users/dpb587/Downloads/2015 Balloon Fiesta/" & filename of vPhoto & "
------
" & description of vPhoto & "
------------
")
		end repeat
		
		return output
	end tell
end run