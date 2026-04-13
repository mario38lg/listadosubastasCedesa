#!/bin/bash
set -e

REGISTRY="registry.gitlab.com/cedesa/listado_subastas_practica"
TAG="${1:-latest}"
BUILDER_NAME="multiarch"

echo "============================================"
echo " Build & Push: $REGISTRY:$TAG"
echo "============================================"

# Verificar directorio
if [ ! -f "artisan" ]; then
    echo "ERROR: Ejecuta este script desde la raiz del proyecto Laravel."
    exit 1
fi

# Instalar dependencias de produccion
echo ""
echo "[1/4] Instalando dependencias de produccion..."
composer install --no-dev --optimize-autoloader --no-interaction

# Configurar Docker Buildx si no existe
echo ""
echo "[2/4] Configurando Docker Buildx..."
if ! docker buildx inspect "$BUILDER_NAME" > /dev/null 2>&1; then
    echo "  Creando builder: $BUILDER_NAME"
    docker buildx create --name "$BUILDER_NAME" --use
    docker buildx inspect --bootstrap
else
    docker buildx use "$BUILDER_NAME"
    echo "  Builder existente: $BUILDER_NAME"
fi

# Build y push multi-arquitectura
echo ""
echo "[3/4] Building imagen multi-arquitectura (amd64 + arm64)..."
echo "  Tag: $REGISTRY:$TAG"

BUILD_ARGS=(
    --platform linux/amd64,linux/arm64
    --cache-from "type=registry,ref=$REGISTRY:cache"
    --cache-to "type=registry,ref=$REGISTRY:cache,mode=max"
    -t "$REGISTRY:$TAG"
    --push
    .
)

# Si el tag no es 'latest', agregar tambien el tag latest
if [ "$TAG" != "latest" ]; then
    BUILD_ARGS+=(-t "$REGISTRY:latest")
fi

docker buildx build "${BUILD_ARGS[@]}"

echo ""
echo "[4/4] Restaurando dependencias de desarrollo..."
composer install --no-interaction

echo ""
echo "============================================"
echo " Imagen publicada correctamente:"
echo "   $REGISTRY:$TAG"
if [ "$TAG" != "latest" ]; then
    echo "   $REGISTRY:latest"
fi
echo "============================================"
