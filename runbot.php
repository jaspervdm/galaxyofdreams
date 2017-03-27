<?php
set_time_limit(0);

while (true) {
  passthru("php bot.php", $ret);
  if ($ret == 20) {
    break;
  }
  sleep(3);
}

echo "Stopped by admin\n";
