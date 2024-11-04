dwpa
====

Distributed WPA PSK auditor



Live installation:

https://wpa-sec.stanev.org

To install dwpa on your server, please refer to [INSTALL.md](INSTALL.md)


To use docker : 

Build the docker image : 

docker build -t dwpa .

Run the docker container : 

docker run -it dwpa

AIO : 

docker build -t dwpa . ; docker run -it --name wpa-sec dwpa
