# Deploy sin git pull y limpieza de GitHub personal en instancia

Este flujo deja de depender de `git pull` en el servidor y despliega por bundle generado en GitHub Actions.

## 1. Que ya queda automatizado

Workflow creado: `.github/workflows/deploy-prod-artifact.yml`

Script remoto creado: `scripts/deploy/deploy_from_bundle.sh`

Flujo:
1. GitHub Actions empaqueta el codigo.
2. El runner sube el bundle por SSH a la instancia.
3. La instancia despliega desde el archivo local.
4. Se ejecutan migraciones y recarga de servicios.

No se usa `git pull` en la instancia.

## 2. Secrets requeridos en GitHub

Configura estos secrets en el repositorio:
1. `EC2_HOST` (ejemplo: 52.23.242.95)
2. `EC2_USER` (normalmente `ec2-user`)
3. `EC2_SSH_PRIVATE_KEY` (llave privada PEM para la instancia)

## 3. Remover cuenta personal de GitHub en la instancia

Ejecuta estos comandos en la instancia (por SSM o SSH):

```bash
set -euo pipefail

APP_DIR="/usr/share/nginx/html/pos-pro-instadosis"

# 1) Backup de configuracion git local del proyecto
if [ -f "$APP_DIR/.git/config" ]; then
  sudo cp "$APP_DIR/.git/config" "$APP_DIR/.git/config.bak.$(date +%Y%m%d%H%M%S)"
fi

# 2) Eliminar remote a GitHub para impedir pulls accidentales
if [ -d "$APP_DIR/.git" ]; then
  cd "$APP_DIR"
  git remote remove origin || true
fi

# 3) Eliminar credenciales globales de GitHub del usuario actual
rm -f ~/.git-credentials || true
git config --global --unset credential.helper || true

# 4) Limpiar entradas globales relacionadas a github (si existen)
for key in user.name user.email github.user; do
  git config --global --unset "$key" || true
done

# 5) (Opcional) revocar claves ssh personales en el usuario actual
# rm -f ~/.ssh/id_rsa ~/.ssh/id_rsa.pub ~/.ssh/id_ed25519 ~/.ssh/id_ed25519.pub || true

# 6) Verificacion
cd "$APP_DIR"
git remote -v || true
```

## 4. Prueba del workflow

1. Haz push a `main`.
2. Revisa el workflow `Deploy Production (Artifact, No Git Pull)`.
3. Valida en servidor:

```bash
sudo systemctl status nginx --no-pager
sudo systemctl status php-fpm --no-pager
php -v
```

## 5. Recomendaciones de hardening

1. Cerrar puerto 3306 publico en Security Group.
2. Crear usuario tecnico dedicado para deploy (no root).
3. Limitar SSH por IP origen del runner/bastion si aplica.
4. Mover a deploy por SSM puro para evitar manejo de llaves SSH en GitHub.
