# Bonifica – XML FUNDAE para Moodle

[![Moodle Plugin CI](https://github.com/Facilo-es/moodle-local_bonifica/actions/workflows/moodle-ci.yml/badge.svg)](https://github.com/Facilo-es/moodle-local_bonifica/actions/workflows/moodle-ci.yml)

**Recupera tu formación bonificada antes y con menos trabajo.**

Cada grupo bonificado termina con un archivo para FUNDAE, y un dato mal puesto lo devuelve. Bonifica prepara ese archivo desde Moodle, con los datos que ya tienes, y lo comprueba con el esquema oficial de FUNDAE antes de que lo subas.

Web y guía: https://bonifica.facilo.es · Hecho por [Facilo](https://facilo.es)

## Gratis

- XML de finalización de grupo, en las versiones de entidad organizadora, empresa bonificada y grupo de empresas.
- Participantes a mano o pegados desde tu hoja de cálculo, con las celdas con errores en rojo.
- Lista clara de lo que falta, con la comprobación de NIF, NIE, teléfonos e importes.

## Pro (disponible próximamente)

- Participantes rellenados desde la matrícula del curso.
- Quién ha finalizado, con la regla del 75 % calculada desde la finalización de actividades y la asistencia.
- XML de acción formativa y de inicio de grupo.
- Avisos del plazo de 7 días para comunicar el inicio.
- Revisión del coste máximo bonificable y de los límites de costes.
- Acceso de solo lectura para inspección e informe de seguimiento en PDF.

Este repositorio contiene solo la edición gratuita. Pro es una versión aparte que reciben quienes la compran, y se instala encima conservando todos los datos.

Tus datos se quedan en tu Moodle. Bonifica no envía nada a nadie. Compatible con el formato XML de FUNDAE 2026. Bonifica comprueba el formato y las reglas conocidas. La aceptación final de cada archivo depende de FUNDAE.

## Instalación

1. Descarga el ZIP desde el directorio de plugins de Moodle o desde las [versiones de este repositorio](https://github.com/Facilo-es/moodle-local_bonifica/releases).
2. En Moodle, ve a *Administración del sitio → Extensiones → Instalar complementos* y sube el ZIP. También puedes descomprimirlo en `local/bonifica`.
3. Abre un curso y entra en *Más → Bonifica*.

Requisitos: Moodle 4.5 a 5.3 y PHP 8.1 o superior. Idiomas: español, inglés, catalán, gallego, euskera, portugués, francés, italiano y alemán.

Guía paso a paso: https://bonifica.facilo.es/guia-xml-finalizacion-fundae-moodle.html

## Soporte

Escríbenos desde https://bonifica.facilo.es/soporte.html.

---

## English

Bonifica builds FUNDAE's XML files (Spain's subsidised training) from Moodle and checks them against FUNDAE's official schema before you upload them.

- **Free:** the group-end XML in all three versions (organising entity, subsidised company, group of companies), participants typed in or pasted from a spreadsheet, and a plain list of what's missing, with NIF, NIE, phone and amount checks.
- **Pro (coming soon):** participants from course enrolments, the 75 % completion rule from activity completion and attendance, the training-action and group-start XML, 7-day start-notice reminders, cost-cap checks, read-only inspector access and a PDF follow-up report.

This repository holds the free edition only. Pro is a separate build that buyers receive; it installs over the free one and keeps all data.

Your data stays in your Moodle; Bonifica sends nothing anywhere. Bonifica checks the format and the known rules; FUNDAE has the final say on every file.

Requires Moodle 4.5 to 5.3. Install from *Site administration → Plugins → Install plugins*, or unzip into `local/bonifica`.

## License

GNU GPL v3 or later. © 2026 Facilo <info@facilo.es>. FUNDAE's schema files in `schemas/` are FUNDAE's own public specification, included unchanged so the files can be checked offline.
