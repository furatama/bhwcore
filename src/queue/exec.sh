
#!/bin/sh

killall php
pkill php

mv EXAMPLE.log EXAMPLE_pre_"`date +%Y%m%d%H%M%S`.log"
nohup php "EXAMPLE.php" >> "EXAMPLE.log" &
