# Estado actual del plugin

- **Versión declarada:** 4.2.2, con autor IlPicasso y compatibilidad declarada hasta WordPress 6.8 y WooCommerce 10.3. 【F:woo-contifico.php†L9-L26】
- **Compatibilidad en el README:** Coincide con la versión 4.2.2 y refleja los mismos mínimos y versiones probadas (WP 6.8 / WC 10.3). 【F:README.txt†L1-L8】
- **Auto‑actualizador:** Se carga `Woo_Contifico_Updater` en `admin_init` y toma por defecto el repositorio `IlPicasso/ContificoWoo` en la rama `main`, pudiendo sobreescribirse vía filtros. 【F:woo-contifico.php†L41-L131】
- **Documentación destacada:** El README detalla cómo configurar las bodegas de Contífico desde la pestaña de integración. 【F:README.txt†L20-L41】

# Seguimiento

- **Compatibilidad declarada:** Actualizada en cabecera del plugin y README. Mantener pruebas cuando haya nuevas versiones de WP/WC para asegurar que los números reflejan la cobertura real.
- **Auto‑actualizador:** Defaults alineados con el repositorio público `IlPicasso/ContificoWoo`. Confirmar que los filtros `woo_contifico_repo_owner`, `woo_contifico_repo_name`, `woo_contifico_repo_branch` y `woo_contifico_repo_access_token` siguen disponibles en despliegues personalizados.
- **Guía QA:** `docs/QA.md` resume los pasos base para validar la conexión, la sincronización y el flujo de facturación con bodegas configuradas.
