# 1) assemble the site from src/ + assets/
FROM python:3.12-alpine AS build
WORKDIR /app
COPY build.py ./
COPY src ./src
COPY assets ./assets
RUN python3 build.py --web

# 2) serve it
FROM nginx:1.27-alpine
COPY deploy/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist/web/ /usr/share/nginx/html/
EXPOSE 8080
HEALTHCHECK CMD wget -qO- http://127.0.0.1:8080/healthz || exit 1
