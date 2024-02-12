FROM nvidia/cuda:12.3.1-devel-ubuntu22.04

# Update distro
RUN apt update && apt dist-upgrade -y

# Install necessary package and clean up
RUN apt install -y wget p7zip-full python3 && rm -rf /var/lib/apt/lists/*

# Set the working directory
WORKDIR /hashcat

# Set volume
VOLUME hashcat-wpa-sec:/hashcat

# Download the hashcat binary
RUN wget https://github.com/hashcat/hashcat/releases/download/v6.2.3/hashcat-6.2.3.7z

# Extract the hashcat binary
RUN 7z x hashcat-6.2.3.7z

# Copy help_carck.py to the container
COPY help_crack/help_crack.py /hashcat/hashcat-6.2.3/help_crack.py

# Set the working directory
WORKDIR /hashcat/hashcat-6.2.3

# Set the entrypoint
ENTRYPOINT ["python3", "/hashcat/hashcat-6.2.3/help_crack.py"]
