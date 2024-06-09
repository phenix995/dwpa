# Run in powershell on windows
# Docker build command
docker build -t hashcat .

# Docker run command
docker run --gpus all -itd --name hashcat-wpa-sec hashcat

# Docker command aio
docker build -t hashcat . ; docker run --gpus all -itd --name hashcat-wpa-sec hashcat