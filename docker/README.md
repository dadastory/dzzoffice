# 本地 Docker Compose 环境

本目录是独立于 DzzOffice 源码的部署包。在官方 `zyx0814/dzzoffice-docker` 的 `app + MariaDB + Redis` 拓扑上扩展了入口 Nginx、BaseMetas FileView 和 ONLYOFFICE Document Server。除入口 Nginx 外，所有服务只位于 Docker 内部网络，未映射宿主机端口。

## 启动

进入本目录。可选：复制环境变量模板并替换本地开发密码。

```bash
cd docker
cp .env.example .env
docker compose up -d --build
```

`app` 采用 `zyx0814/dzzoffice-docker` 的官方 Dockerfile 运行模型：以 PHP-FPM Alpine 为基础安装 Nginx、Supervisor 和 Dzz 所需扩展。构建上下文是父目录（即当前检出的 DzzOffice 源码），源码会复制到镜像的 `/usr/src/dzzoffice`；官方入口脚本只会在首次启动、运行目录为空时将其同步到 `docker/runtime/dzzoffice/site/`。源码目录不会被容器写入。

入口 Nginx 仅绑定 `127.0.0.1:${DZZ_HTTP_PORT:-8080}`。访问 DzzOffice：

```text
http://dzz.localhost:8080/
```

`dzz.localhost` 在现代浏览器中会解析到本机；Compose 也为入口容器提供同名内部网络别名，以便 Document Server 能回调 DzzOffice。

## 路由边界

| 浏览器地址 | 后端服务 | 说明 |
| --- | --- | --- |
| `http://dzz.localhost:8080/` | DzzOffice | 默认路由；所有未明确匹配的路径都转发到 DzzOffice。 |
| `http://dzz.localhost:8080/apps/fileview/` | BaseMetas FileView | 按官方子目录代理方式部署；仅用于已安装的 FileView 插件调用。 |
| `http://dzz.localhost:8080/apps/onlyoffice/` | ONLYOFFICE Document Server | 使用 ONLYOFFICE 官方 virtual-path 代理方式；同域名、同源访问。 |

FileView 不绑定宿主机端口，入口 Nginx 使用 `/apps/fileview/` 作为唯一访问路径，并传递官方要求的 `X-Forwarded-Prefix` 等反向代理头。服务直接使用官方 `basemetas/fileview:latest` 镜像；本部署不为其挂载存储目录，不会在本地维护独立的用户文件库。FileView 插件会让 FileView 容器回读浏览器可见的 Dzz 地址，因此本地请统一从 `http://dzz.localhost:8080/` 打开 DzzOffice，并在插件中填写 `http://dzz.localhost:8080/apps/fileview`（末尾不要 `/`）；不要使用 `localhost` 或 `127.0.0.1`。

ONLYOFFICE 的 `/apps/onlyoffice/` 规则会在转发到 Document Server 时去掉此前缀，同时传递包含此前缀的 `X-Forwarded-Host`。这是 ONLYOFFICE 官方 virtual-path 示例使用的方式，编辑器资源、WebSocket 与回调均会保留在该路径下。

在 DzzOffice 的 ONLYOFFICE 应用配置中使用 `http://dzz.localhost:8080/apps/onlyoffice/`，并将 `ONLYOFFICE_JWT_SECRET` 配置为与 Document Server 一致的值。若从另一台设备访问，请将文档中的 `dzz.localhost:8080` 替换为实际的同一个公网域名与端口；不要为 ONLYOFFICE 单独设置域名。

## 安全与限制

- `ONLYOFFICE_JWT_SECRET` 的默认值仅用于本地；非本机环境必须改成高熵随机值，并同步到 Dzz 的 ONLYOFFICE 配置。
- Compose 绑定到 loopback，便于本地测试。生产环境必须由 TLS 反代保护公网入口。
- 默认 `DZZ_GATEWAY_AUTH_MODE=off`，保持旧版 DzzOffice、既有插件和当前路由行为不变。此模式下 `/apps/fileview/` 与 `/apps/onlyoffice/` 只依靠不暴露服务端口的网络边界。
- 安装包含 `dzz/gateway/authcheck.php` 的 DzzOffice 版本后，可设置高熵的 `DZZ_GATEWAY_AUTH_SECRET`，并设置 `DZZ_GATEWAY_AUTH_MODE=dzz-authcheck`。网关会把原始 Dzz 登录 Cookie 交给 Dzz 校验；未登录请求会得到 `401`，不会转发到第三方服务。两个变量必须同时传给 `app` 与 `gateway`，Compose 已自动完成。
- 鉴权接口只接受网关的共享密钥，且只返回 `204`、`401`、`403` 或未启用时的 `404`，不会返回用户资料。Nginx 的鉴权子路径是 `internal`，无法直接从浏览器访问。
- 回滚只需将 `DZZ_GATEWAY_AUTH_MODE` 改回 `off` 后执行 `docker compose up -d gateway`；不会改变 DzzOffice 的 Cookie、会话或数据库结构。

## 运行数据与升级

不使用 Docker named volume。所有持久化数据均在本目录的 `runtime/` 下：

| 本地目录 | 内容 |
| --- | --- |
| `runtime/mariadb/` | MariaDB 数据库 |
| `runtime/redis/` | Redis AOF 持久化文件 |
| `runtime/dzzoffice/site/` | DzzOffice 首次启动后初始化的完整站点、配置与上传文件 |
| `runtime/onlyoffice/` | ONLYOFFICE 数据、日志和运行库 |

`docker compose down` 不会删除这些目录。为避免覆盖已安装的 DzzOffice，已有的 `runtime/dzzoffice/site/` 不会在 `--build` 后自动用新源码替换；升级应用源码前请先备份该目录，再按升级策略同步代码与保留的 `data/`、`config/`。

## 验证

```bash
cd docker
docker compose config
docker compose up -d --build
docker compose ps
```

启动后，请确认 DzzOffice 默认路由、FileView 的 `/apps/fileview/preview/welcome` 和 ONLYOFFICE 的 `/apps/onlyoffice/` 代理均可访问。

启用网关会话校验后，可执行本地回归脚本。它会临时生成管理员会话 Cookie，仅用于验证并在结束时删除：

```bash
docker/tests/gateway-authcheck.sh
```
