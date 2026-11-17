<?php

declare(strict_types=1);

/*
 * A one-call stand-in for `webhooktrap serve-sandbox` (tests only): listens
 * on argv[1], accepts one connection, reads the length-prefixed frame and
 * writes it to argv[1].frame, then answers per argv[2]:
 *   reply <file>    writes the bytes of that file and closes
 *   silent          never answers (client timeout)
 */
$path = $argv[1];
$mode = $argv[2];
@unlink($path);
$server = stream_socket_server('unix://'.$path, $errno, $errstr);
if ($server === false) {
    fwrite(STDERR, "listen failed\n");
    exit(1);
}
$conn = @stream_socket_accept($server, 10);
if ($conn === false) {
    exit(1);
}
$len = unpack('N', (string) fread($conn, 4))[1];
$frame = '';
while (strlen($frame) < $len && ! feof($conn)) {
    $frame .= (string) fread($conn, $len - strlen($frame));
}
file_put_contents($path.'.frame', $frame);
if ($mode === 'silent') {
    sleep(5);
} else {
    fwrite($conn, (string) file_get_contents($argv[3]));
}
fclose($conn);
fclose($server);
@unlink($path);
