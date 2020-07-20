#!/bin/bash -e

VERSION=$1
if [[ -z ${VERSION} ]]
then
	echo "Usage: $0 domjudge-version"
	echo "	For example: $0 5.3.0"
	exit 1
fi

if [ "${BUILD_DOWNLOAD_RELEASE-0}" != 0 ]; then
URL=https://www.domjudge.org/releases/domjudge-${VERSION}.tar.gz
FILE=domjudge.tar.gz

echo "[..] Downloading DOMJuge version ${VERSION}..."

if ! curl -f -s -o ${FILE} ${URL}
then
	echo "[!!] DOMjudge version ${VERSION} file not found on https://www.domjudge.org/releases"
	exit 1
fi

echo "[ok] DOMjudge version ${VERSION} downloaded as domjudge.tar.gz"; echo
fi

if [ "${BUILD_FROM_SOURCE-1}" != 0 ]; then
echo "[..] Boostrapping..."
tar c -C .. --exclude=./.git --exclude="./docker/*.tar.gz" . |
	docker build --target=dist -t domjudge/dist:${VERSION} -f docker/domserver/Dockerfile.source -
docker run --rm domjudge/dist:${VERSION} tar cz -C /domjudge-src domjudge > domjudge.tar.gz
docker rmi --no-prune domjudge/dist:${VERSION}
echo "[ok] Done boostrapping"
fi

if [ "${BUILD_DOMSERVER-1}" != 0 ]; then
echo "[..] Building Docker image for domserver using intermediate build image..."
docker build -t domjudge/domserver:${VERSION} -f domserver/Dockerfile .
echo "[ok] Done building Docker image for domserver"
fi

if [ "${BUILD_JUDGEHOST-1}" != 0 ]; then
echo "[..] Building Docker image for judgehost using intermediate build image..."
docker build -t domjudge/judgehost:${VERSION}-build -f judgehost/Dockerfile.build .
docker rm -f domjudge-judgehost-${VERSION}-build > /dev/null 2>&1 || true
docker run --name domjudge-judgehost-${VERSION}-build --privileged domjudge/judgehost:${VERSION}-build
docker cp domjudge-judgehost-${VERSION}-build:/chroot.tar.gz .
docker cp domjudge-judgehost-${VERSION}-build:/judgehost.tar.gz .
docker rm -f domjudge-judgehost-${VERSION}-build
docker rmi domjudge/judgehost:${VERSION}-build
docker build -t domjudge/judgehost:${VERSION} -f judgehost/Dockerfile .
echo "[ok] Done building Docker image for judgehost"
fi

if [ "${BUILD_DEFAULT_JUDGEHOST_CHROOT-0}" != 0 ]; then
echo "[..] Building Docker image for judgehost chroot..."
docker build -t domjudge/default-judgehost-chroot:${VERSION} -f judgehost/Dockerfile.chroot .
echo "[ok] Done building Docker image for judgehost chroot"
fi

if [ "${BUILD_PRINT_HELP-0}" != 0 ]; then
echo "All done. Image domjudge/domserver:${VERSION} and domjudge/judgehost:${VERSION} created"
echo "If you are a DOMjudge maintainer with access to the domjudge organization on Docker Hub, you can now run the following command to push them to Docker Hub:"
echo "$ docker push domjudge/domserver:${VERSION} && docker push domjudge/judgehost:${VERSION} && docker push domjudge/default-judgehost-chroot:${VERSION}"
echo "If this is the latest release, also run the following command:"
echo "$ docker tag domjudge/domserver:${VERSION} domjudge/domserver:latest && \
docker tag domjudge/judgehost:${VERSION} domjudge/judgehost:latest && \
docker tag domjudge/default-judgehost-chroot:${VERSION} domjudge/default-judgehost-chroot:latest && \
docker push domjudge/domserver:latest && docker push domjudge/judgehost:latest && docker push domjudge/default-judgehost-chroot:latest"
fi
