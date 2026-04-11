# Define base image
FROM centos:7.9.2009
MAINTAINER kingsley
LABEL author="kingsley"
LABEL org.opencontainers.image.authors="kingsley"

# Set timezone
ENV TZ=Asia/Singapore
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Replace Centos 7 repo
COPY resource/CentOS-Base.repo /etc/yum.repos.d/CentOS-Base.repo

# Install epel repo
RUN yum install -y epel-release
RUN yum update -y

# Install dependencies
RUN yum install -y dejavu-sans-fonts gcc rsync make openssh-clients

# Install libwebp
WORKDIR /opt/libwebp
RUN curl -O https://downloads.webmproject.org/releases/webp/libwebp-1.1.0.tar.gz -k
RUN tar -xf libwebp-1.1.0.tar.gz
RUN mv libwebp-1.1.0/* .
RUN rm -rf libwebp-1.1.0.tar.gz libwebp-1.1.0
RUN ./configure --prefix=/usr --enable-libwebpmux --enable-libwebpdemux --enable-libwebpdecoder --enable-libwebpextras --enable-swap-16bit-csp --disable-static
RUN make -j $(nproc)
RUN make install && ldconfig /usr/lib

# Install ffmpeg
WORKDIR /home/ffmpeg
RUN curl -O https://www.johnvansickle.com/ffmpeg/old-releases/ffmpeg-6.0-amd64-static.tar.xz -k
RUN xz -d ffmpeg-6.0-amd64-static.tar.xz
RUN tar -xvf ffmpeg-6.0-amd64-static.tar
RUN mv ffmpeg-6.0-amd64-static/* .
RUN rm -rf ffmpeg-6.0-amd64-static.*
RUN chmod -R 755 ffmpeg
RUN sed -i '$a''export PATH=$PATH:/home/ffmpeg' /etc/profile
RUN source /etc/profile

# Create working directory
WORKDIR /home/video_cut_go
COPY resource/font/bos.ttf /home/video_cut_go/font/bos.ttf
COPY resource/video_cut_go /home/video_cut_go

ARG UPDATE_NOTES="None"
LABEL update_notes="$UPDATE_NOTES"

ENTRYPOINT ["/home/video_cut_go/video_cut_go"]