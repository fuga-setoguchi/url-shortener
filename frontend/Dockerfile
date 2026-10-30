FROM node:26-alpine

WORKDIR /app

# node_modules は名前付きボリュームに置く（ホストのOS向けのバイナリと混ざらないようにする）
RUN mkdir node_modules && chown node:node /app node_modules

# コンテナ内のプロセスはrootで実行しない
USER node

CMD ["sh", "-c", "npm ci && exec npm run dev -- --host 0.0.0.0"]
