/**
 * Lee el código de verificación en dos pasos del último correo guardado en storage/correos
 * (en desarrollo, sin SMTP, el sistema guarda ahí los correos en vez de enviarlos).
 */
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const carpeta = join(dirname(fileURLToPath(import.meta.url)), '..', '..', '..', 'storage', 'correos');

/** Espera (hasta 10 s) un correo con código creado después de «desde» (ms) y devuelve el código. */
export async function leerCodigo2fa(desde) {
  for (let intento = 0; intento < 40; intento++) {
    let archivos = [];
    try {
      archivos = readdirSync(carpeta).filter((a) => a.endsWith('.eml'))
        .map((a) => ({ ruta: join(carpeta, a), fecha: statSync(join(carpeta, a)).mtimeMs }))
        .filter((a) => a.fecha >= desde - 1500)
        .sort((a, b) => b.fecha - a.fecha);
    } catch { /* la carpeta aún no existe */ }
    for (const { ruta } of archivos) {
      const codigo = readFileSync(ruta, 'utf8').match(/Subject: Código de acceso (\d{6})/)?.[1];
      if (codigo) return codigo;
    }
    await new Promise((r) => setTimeout(r, 250));
  }
  throw new Error('No llegó el correo con el código de verificación a storage/correos (¿está configurado SMTP?).');
}
