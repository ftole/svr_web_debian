#!/bin/bash
# Prueba real end-to-end: login a phpMyAdmin y busqueda de avisos
HOST="${PMA_HOST:-webdev.empresa.local}"
USER="${PMA_USER_TEST:-webadmin}"
PASS="${PMA_PASS_TEST:-Temp123#}"
RES=(--resolve "${HOST}:443:127.0.0.1")
CJ=$(mktemp)
BODY=$(mktemp)

curl -sk -c "$CJ" -o "$BODY" "${RES[@]}" "https://${HOST}/index.php"
TOKEN=$(sed -n 's/.*name="token" value="\([^"]*\)".*/\1/p' "$BODY" | head -1)
echo "TOKEN capturado: ${TOKEN:0:10}..."

CODE=$(curl -sk -L -b "$CJ" -c "$CJ" -o "$BODY" -w '%{http_code}' "${RES[@]}" \
  --data-urlencode "pma_username=${USER}" \
  --data-urlencode "pma_password=${PASS}" \
  --data-urlencode "server=1" \
  --data-urlencode "token=${TOKEN}" \
  "https://${HOST}/index.php")
echo "HTTP final tras login: ${CODE}"

echo "=== LOGIN ==="
if grep -qiE 'pma_username|Cannot log in|Access denied for user' "$BODY"; then
  echo "RESULTADO: LOGIN FALLIDO"
else
  echo "RESULTADO: LOGIN OK (panel cargado)"
fi

echo "=== AVISO ALMACENAMIENTO ==="
if grep -qi 'configuration storage is not completely configured\|almacenamiento de configur' "$BODY"; then
  echo "RESULTADO: AVISO_PRESENTE"
else
  echo "RESULTADO: AVISO_AUSENTE"
fi

echo "=== ADVERTENCIAS DEPRECADAS TWIG ==="
if grep -qiE 'getExpressionParser|ExpressionParser::parseExpression|Since twig/twig 3\.21' "$BODY"; then
  echo "RESULTADO: DEPRECACIONES_PRESENTES"
else
  echo "RESULTADO: DEPRECACIONES_AUSENTES"
fi

rm -f "$CJ" "$BODY"
