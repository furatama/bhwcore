
#!/bin/sh

killall php
pkill php

nohup php "example.php" >> "example.log" &

# nohup php livemgp.php
