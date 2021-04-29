WiringPi-php-bme280
========
BME280 で温度・湿度・気圧・暑さ指数を記録する

/opt/bme280.php

起動
```
cd /opt
./bme280.php
```

停止
```
cd /opt
./bme280.php stop
```

自動起動
```
vi /etc/rc.local
exit の前に

/opt/bme280.php
```
WEB
apache の場合

```
html/* を /var/www/html/*
chmod -R 0644 /var/www/html/*
```
