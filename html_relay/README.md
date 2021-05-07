複数測定データの一括表示

![bme280s](https://user-images.githubusercontent.com/76575923/117416962-da543f00-af54-11eb-96e4-b2c532e57921.png)

============
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
