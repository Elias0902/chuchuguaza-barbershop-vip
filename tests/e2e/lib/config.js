/**
 * Configuración de las pruebas: lee tests/e2e/.env (si existe) y las variables de entorno.
 * La contraseña nunca se escribe en el código: va en .env, que no se sube al repositorio.
 */
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const raiz = join(dirname(fileURLToPath(import.meta.url)), '..');
const archivo = join(raiz, '.env');

if (existsSync(archivo)) {
  for (const linea of readFileSync(archivo, 'utf8').split(/\r?\n/)) {
    const limpia = linea.trim();
    if (!limpia || limpia.startsWith('#') || !limpia.includes('=')) continue;
    const [clave, ...resto] = limpia.split('=');
    if (process.env[clave.trim()] === undefined) process.env[clave.trim()] = resto.join('=').trim();
  }
}

const requerida = (nombre) => {
  const valor = process.env[nombre];
  if (!valor) throw new Error(`Falta ${nombre}. Copia tests/e2e/.env.example como .env y complétalo.`);
  return valor;
};

export const config = {
  urlBase: (process.env.URL_BASE || 'http://localhost/chuchuguaza-barber-shop').replace(/\/$/, ''),
  usuario: requerida('TEST_USUARIO'),
  clave: requerida('TEST_CLAVE'),
  navegador: (process.env.NAVEGADOR || 'chrome').toLowerCase(),
  oculto: process.env.OCULTO === '1',
  pausa: Number(process.env.PAUSA_MS || 0),
  espera: 15000,
  carpetaCapturas: join(raiz, 'capturas'),
};
