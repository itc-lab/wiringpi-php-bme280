WiringPi-php-bme280
========
BME280 で温度・湿度・気圧・暑さ指数を表示
![bme280](https://user-images.githubusercontent.com/76575923/120967884-0efd2580-c7a3-11eb-9433-9c1f2168d01a.jpg)

```
/opt/bme280.php
chmod 0755 /opt/bme280.php
```

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
WEB<br/>
(apache の場合)

```
html/* を /var/www/html/*
chmod -R 0644 /var/www/html/*
chmod 0755 /var/www/html/js
```
