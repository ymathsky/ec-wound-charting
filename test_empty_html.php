<?php
$tests = [
    '',
    ' ',
    '<br>',
    '<br/>',
    '<br />',
    '<p></p>',
    '<p><br></p>',
    " \n ",
    "<div>  </div>"
];

foreach ($tests as $t) {
    echo "content: [" . htmlspecialchars($t) . "] -> ";
    $plain = trim(strip_tags($t));
    echo "stripped: [" . $plain . "] -> ";
    echo "Is empty? " . (empty($plain) ? "YES" : "NO") . "\n";
}
?>