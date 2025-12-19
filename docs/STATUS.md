# Estado actual del plugin

- **Versión declarada:** 4.1.98, con autor IlPicasso y compatibilidad declarada hasta WordPress 6.8 y WooCommerce 10.3. 【F:woo-contifico.php†L9-L26】
- **Compatibilidad en el README:** Coincide con la versión 4.1.98 y refleja los mismos mínimos y versiones probadas (WP 6.8 / WC 10.3). 【F:README.txt†L1-L8】
- **Auto‑actualizador:** Se carga `Woo_Contifico_Updater` en `admin_init` y toma por defecto el repositorio `IlPicasso/ContificoWoo` en la rama `main`, pudiendo sobreescribirse vía filtros. 【F:woo-contifico.php†L41-L131】
- **Documentación destacada:** El README documenta en detalle la compatibilidad con MultiLoca y el flujo de sincronización por ubicaciones. 【F:README.txt†L20-L63】

# Seguimiento

- **Compatibilidad declarada:** Actualizada en cabecera del plugin y README. Mantener pruebas cuando haya nuevas versiones de WP/WC para asegurar que los números reflejan la cobertura real.
- **Auto‑actualizador:** Defaults alineados con el repositorio público `IlPicasso/ContificoWoo`. Confirmar que los filtros `woo_contifico_repo_owner`, `woo_contifico_repo_name`, `woo_contifico_repo_branch` y `woo_contifico_repo_access_token` siguen disponibles en despliegues personalizados.
- **MultiLoca:** La integración continúa documentada con los flujos actuales; ejecutar una validación completa con las variantes recientes del plugin de terceros tras actualizaciones mayores de WooCommerce para asegurar que se mantienen los comportamientos descritos.
- **Guía QA:** `docs/QA.md` resume los pasos para probar detección automática/manual y la ruta de metadatos `wcmlim_stock_at_{ID}` en productos simples y variables.
