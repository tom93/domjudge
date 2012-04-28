<?php
/**
 * Show source code from the database.
 *
 * Part of the DOMjudge Programming Contest Jury System and licenced
 * under the GNU GPL. See README and COPYING for details.
 */

function parseSourceDiff($difftext){
	$line = strtok($difftext,"\n"); //first line
	$return = '';
	while ( strlen($line) != 0 ) {
		// Strip any additional DOS/MAC newline characters:
		$line = trim($line, "\r\n");
		switch ( substr($line,0,1) ) {
		case '-':
			$formdiffline = "<span class='diff-old'>".htmlspecialchars($line)."</span>";
			break;
		case '+':
			$formdiffline = "<span class='diff-new'>".htmlspecialchars($line)."</span>";
			break;
		default:
			$formdiffline = htmlspecialchars($line);
		}
		$return .= $formdiffline . "\n";
		$line = strtok("\n");
	}
	return $return;
}

function createDiff($source, $newfile, $id, $oldsource, $oldfile, $oldid) {
	// Try different ways of diffing, in order of preference.
	if ( function_exists('xdiff_string_diff') ) {
		// The PECL xdiff PHP-extension.

		$difftext = xdiff_string_diff($oldsource['sourcecode'],
					      $source['sourcecode'],2);

	} elseif ( !(bool) ini_get('safe_mode') ||
		       strtolower(ini_get('safe_mode'))=='off' ) {
		// Only try executing diff when safe_mode is off, otherwise
		// the shell_exec will fail.

		if ( is_readable($oldfile) && is_readable($newfile) ) {
			// A direct diff on the sources in the SUBMITDIR.

			$difftext = `diff -Bt -U 2 $oldfile $newfile 2>&1`;

		} else {
			// Try generating temporary files for executing diff.

			$oldfile = mkstemps(TMPDIR."/source-old-s$oldid-XXXXXX",0);
			$newfile = mkstemps(TMPDIR."/source-new-s$id-XXXXXX",0);

			if( ! $oldfile || ! $newfile ) {
				$difftext = "DOMjudge: error generating temporary files for diff.";
			} else {
				$oldhandle = fopen($oldfile,'w');
				$newhandle = fopen($newfile,'w');

				if( ! $oldhandle || ! $newhandle ) {
					$difftext = "DOMjudge: error opening temporary files for diff.";
				} else {
					if ( (fwrite($oldhandle,$oldsource['sourcecode'])===FALSE) ||
					     (fwrite($newhandle,   $source['sourcecode'])===FALSE) ) {
						$difftext = "DOMjudge: error writing temporary files for diff.";
					} else {
						$difftext = `diff -Bt -U 2 $oldfile $newfile 2>&1`;
					}
				}
				if ( $oldhandle ) fclose($oldhandle);
				if ( $newhandle ) fclose($newhandle);
			}

			if ( $oldfile ) unlink($oldfile);
			if ( $newfile ) unlink($newfile);
		}
	} else {
		$difftext = "DOMjudge: diff functionality not available in PHP or via shell_exec.";
	}

	return $difftext;
}

function presentSource ($sourcedata, $langid)
{
	$head = '<div class="tabbertab">' .
		'<h2 class="filename"><a name="source' . htmlspecialchars($sourcedata['rank']) .
		'"></a>' .
		htmlspecialchars($sourcedata['filename']) . "</h2> <a " .
		"href=\"show_source.php?id=" . htmlspecialchars($sourcedata['submitid']) .
		"&amp;fetch=" . htmlspecialchars($sourcedata['rank']) .
		"\"><img class=\"picto\" src=\"../images/b_save.png\" alt=\"download\" title=\"download\" /></a>\n\n";

	if ( strlen($sourcedata['sourcecode'])==0 ) {
		// Someone submitted an empty file. Cope gracefully.
		$head .= "<p class=\"nodata\">empty file</p>\n\n";
	} else if ( strlen($sourcedata['sourcecode']) < 10 * 1024 ) {
		// Source < 10kB (for longer source code,
		// highlighter tends to take very long time or timeout)
		$head .= highlight($sourcedata['sourcecode'], $langid);
	} else {
		$head .= highlight_native($sourcedata['sourcecode'], $langid);
	}

	return $head .= '</div>';
}

function presentDiff ($old, $new)
{
	$oldsourcefile = getSourceFilename($old);
	$newsourcefile = getSourceFilename($new);

	$difftext = createDiff($new['sourcecode'], SUBMITDIR.'/'.$newsourcefile, $new['submitid'],
	                       $old['sourcecode'], SUBMITDIR.'/'.$oldsourcefile, $old['submitid']);

	$oldid = htmlspecialchars($old['submitid']);
	return '<div class="tabbertab">' .
		'<h2 class="filename"><a name="diff"></a>' .
		htmlspecialchars($old['filename']) . "</h2>\n\n" .

		'<pre class="output_text">' . parseSourceDiff($difftext) . "</pre>\n\n" .
		'</div>';
}



require('init.php');

// FIXME/questions:
// - content-disposition: attachment instead of inline, content-type octet-stream?
// - filenames: display team filenames (a.java) or 'domjudge' filenames (c1.s2.bla..)?
//   for downloads: same question do we want the 'domjudge' filenames at all?
//   alternative: store on disk as SUBMITDIR/c1/s12.etc/a.java. This does lose some of the
//   metadata (rank) which is currently encoded in the filename. Or: use 'domjudge' filenames
//   ONLY on disk.
// - does table submission_file need UNIQUE on (submitid,filename) and on (submitid,rank)?
// - code allows for some refactoring and layout polishing
// - edit multiple source

$id = (int)$_GET['id'];
$submission = $DB->q('MAYBETUPLE SELECT * FROM submission s
	      WHERE submitid = %i',$id);
if ( empty($submission) ) error ("Submission $id not found");

// Download was requested
if ( isset($_GET['fetch']) ) {

	$row = $DB->q('TUPLE SELECT filename, sourcecode FROM submission_file
	               WHERE submitid = %i AND rank = %i', $id, $_GET['fetch']);
	header("Content-Type: text/plain; name=\"" . $row['filename'] . "\"; charset=" . DJ_CHARACTER_SET);
	header("Content-Disposition: inline; filename=\"" . $row['filename'] . "\"");
	header("Content-Length: " . strlen($row['sourcecode']));

	echo $row['sourcecode'];
	exit;
}

$title = "Source: s$id";
require(LIBWWWDIR . '/header.php');
require(LIBWWWDIR . '/highlight.php');

echo "<h2>Source code for submission s" .htmlspecialchars($id);
if ( !empty($submission['origsubmitid']) ) {
	echo  " (resubmit of s" . htmlspecialchars($submission['origsubmitid']) . ")";
}
echo "</h2>\n\n";


// display highlighted content of the source files
$sources = $DB->q('TABLE SELECT *
                   FROM submission_file LEFT JOIN submission USING(submitid)
                   WHERE submitid = %i ORDER BY rank', $id);

$html = '<script type="text/javascript" src="../js/tabber.js"></script>' .
	'<div class="tabber">';
foreach($sources as $sourcedata)
{
	$html .= presentSource($sourcedata, $submission['langid']);	
}
$html .= "</div>";

// display diff between previous and/or original submission

if ($submission['origsubmitid']) {
	$origdata    = $DB->q('TUPLE SELECT * FROM submission
	                       WHERE submitid = %i', $submission['origsubmitid']);
	$origsources = $DB->q('TABLE SELECT * FROM submission_file
	                       WHERE submitid = %i', $submission['origsubmitid']);
	$olddata     = $DB->q('MAYBETUPLE SELECT * FROM submission
	                       WHERE teamid = %s AND probid = %s AND langid = %s AND submittime < %s
	                       AND origsubmitid = %i ORDER BY submittime DESC LIMIT 1',
	                      'domjudge',$submission['probid'],$submission['langid'],
	                      $submission['submittime'], $submission['origsubmitid']);
	$oldsources  = $DB->q('TABLE SELECT * FROM submission_file
	                       WHERE submitid = %i', $olddata['submitid']);
} else {
	$olddata     = $DB->q('MAYBETUPLE SELECT * FROM submission
	                       WHERE teamid = %s AND probid = %s AND langid = %s AND submittime < %s
	                       ORDER BY submittime DESC LIMIT 1',
	                      $submission['teamid'],$submission['probid'],$submission['langid'],
	                      $submission['submittime']);
	$oldsources  = $DB->q('TABLE SELECT * FROM submission_file
	                       WHERE submitid = %i', $olddata['submitid']);
}

if ($olddata !== NULL) {
	$oldid = $olddata['submitid'];
	$html .= "<h2>Diff to submission <a href=\"submission.php?id=$oldid\">s$oldid</a></h2>\n";

	// if both current and previous submission have just one file, diff them directly
	if (count($sources) == 1 && count($oldsources) == 1 ) {
		// FIXME: edit/resubmit, including diffs currently only supports single files
		$html .= "(<a href=\"edit_source.php?id=$id\">edit</a>)\n\n";

		$html .= presentDiff ( array_merge($oldsources[0],$olddata), $sources[0] );
	} else {
		$newfilenames = $fileschanged = $filesunchanged = array();
		foreach($sources as $newsource) {
			$oldfilenames = array();
			foreach($oldsources as $oldsource) {
				if($newsource['filename'] == $oldsource['filename']) {
					if ( $oldsource['sourcecode'] == $newsource['sourcecode'] ) {
						$filesunchanged[] = $newsource['filename'];
					} else {
						$fileschanged[] = $newsource['filename'];
						$diffhtml .= presentDiff ( array_merge($oldsource,$olddata), $newsource );
					}
				}
				$oldfilenames[] = $oldsource['filename'];
			}
			$newfilenames[] = $newsource['filename'];
		}
		$filesadded   = array_diff($newfilenames,$oldfilenames);
		$filesremoved = array_diff($oldfilenames,$newfilenames);

		$html .= "<table>\n";
		if ( count($filesadded)>0 ) {
			$html .= "<tr><td class=\"diff-add\">Files added:</td><td class=\"filename\">" .
				implode(' ', $filesadded) . "</td></tr>\n";
		}
		if ( count($filesremoved)>0 ) {
			$html .= "<tr><td class=\"diff-del\">Files removed:</td>" .
				"<td class=\"filename\">" . implode(' ', $filesremoved) . "</td></tr>\n";
		}
		if ( count($fileschanged)>0 ) {
			$html .= "<tr><td class=\"diff-changed\">Files changed:</td>" .
			    "<td class=\"filename\">" . implode(' ', $fileschanged) . "</td></tr>\n";
		}
		if ( count($filesunchanged)>0 ) {
			$html .= "<tr><td>Files unchanged:</td><td class=\"filename\">" .
				implode(' ', $filesunchanged) . "</td></tr>\n";
		}
		$html .= "</table>\n\n";
		$html .= "<div class=\"tabber\">\n" . $diffhtml . "</div>\n";
	}
}

// FIXME: edit/resubmit, including diffs currently only supports single files
if ( !empty($origsources) ) {
	$html .= "<h2>Diff to original submission</h2>\n\n";
	$html .= presentDiff ( array_merge($origsources[0],$origdata), $sources[0] );

}

echo $html;

require(LIBWWWDIR . '/footer.php');
