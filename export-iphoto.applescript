on run argv
	tell application "iPhoto"
		set vAlbum to first item of (get every album whose name is (item 1 of argv))
		set vPhotos to get every photo in vAlbum
		
		set output to ""
		
		repeat with vPhoto in vPhotos
			set output to output & Â
				"altitude: " & altitude of vPhoto & "
" & Â
				"latitude: " & latitude of vPhoto & "
" & Â
				"longitude: " & longitude of vPhoto & "
" & Â
				"name: " & name of vPhoto & "
" & Â
				"date: " & date of vPhoto & "
" & Â
				"path: " & original path of vPhoto & "
" & Â
				"title: " & title of vPhoto & "
------
" & comment of vPhoto & "
------------
"
		end repeat
		
		return output
	end tell
end run