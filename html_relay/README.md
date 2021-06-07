複数測定データの一括表示
============
![bme280s](https://user-images.githubusercontent.com/76575923/120968278-9cd91080-c7a3-11eb-95b6-d9322c7146fc.jpg)

複数サイトを１画面に表示すると XSS になるので、１サーバー経由で表示

php-curl を追加
```
apt-get -y install php-curl
systemctl restart apache2
```

TIME_WAIT でソケットが枯渇する可能性があるので、ソケットの解放を早くする

Raspberry<br/>
```
vi /etc/sysctl.conf
	net.ipv4.tcp_tw_reuse = 1
	net.ipv4.tcp_fin_timeout = 10
  
sysctl -p 
```

Windows
```
HKEY_LOCAL_MACHINE\SYSTEM\CurrentControlSet\services\Tcpip\Parameters\TcpTimedWaitDelay=dword:0000000a

再起動
```
