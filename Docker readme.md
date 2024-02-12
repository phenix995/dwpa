# Docker build command
docker build -t hashcat .

# Docker run command
docker run --gpus all -it --name hashcat-wpa-sec hashcat
