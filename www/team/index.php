<?php
/**
 * $Id$
 */

require('init.php');
$refresh = '30;url=./';
$title = 'Team overview';
require('../header.php');

include('menu.php');

echo "<h1>Teampage ".htmlentities($name)."</h1>\n\n";

// call getSubmissions function from common.php for this team.
getSubmissions('team', $login);

require('../footer.php');
