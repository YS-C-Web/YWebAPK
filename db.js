// ============================================
// db.js - Base de datos de APKs para YSWEBAPK
// ============================================

const APPS_DB = [
  {
    id: 1,
    nombre: "4 Fotos 1 Palabra",
    version: "1.0",
    categoria: "juego",
    descripcion: "Te mostraremos 4 imagenes, según su contexto debes adivinar una palabra con las letras que te daremos.",
    icono: "icons/4fotos1palabra.jpeg",
    tamaño: "0.00 MB",
    fecha: ".",
    link: "apks/4fotos1palabra.apk",
    screenshots: ["screenshots/4f1p1.jpg", "screenshots/4f1p2.jpg"],
    enDesarrollo: true
  },
  {
    id: 2,
    nombre: "Contador de Dinero Profesional",
    version: "1.0",
    categoria: "utilidad",
    descripcion: "Aplicación profesional para contar y organizar tu dinero de manera rápida y eficiente.",
    icono: "icons/Icocontpro.png",
    tamaño: "4.67 MB",
    fecha: "2026-08-09",
    link: "apks/Contador_de_Dinero_Profesional.apk",
    screenshots: ["screenshots/CdDP0.gif", "screenshots/CdDP1.jpg", "screenshots/CdDP2.jpg"],
  },
  {
    id: 3,
    nombre: "Contador de Dinero Universal",
    version: "1.3",
    categoria: "utilidad",
    descripcion: "Contador de dinero universal con soporte para múltiples monedas.",
    icono: "icons/IcoCdDU.png",  // Reutilizado para este, pero podría ser otro
    tamaño: "5.06 MB",
    fecha: "2026-08-09",
    link: "apks/Contador_de_Dinero_Universal.apk",
    screenshots: ["screenshots/CdDU0.gif", "screenshots/CdDU1.jpg", "screenshots/CdDU2.jpg"]
  },
  {
    id: 4,
    nombre: "Conversor de Unidades",
    version: "1.0",
    categoria: "utilidad",
    descripcion: "Convierte fácilmente entre diferentes unidades de medida: longitud, peso, volumen, temperatura y más.",
    icono: "icons/Icoconvunid.png",  // Icono de Conversor de Unidades
    tamaño: "4.71 MB",
    fecha: "2026-08-09",
    link: "apks/Conversor_de_Unidades.apk",
    screenshots: ["screenshots/CdU0.gif", "screenshots/CdU1.jpg", "screenshots/CdU2.jpg"]  // cdpu = Conversor De Peso Universal
  },
  {
    id: 5,
    nombre: "Velocímetro",
    version: "1.6",
    categoria: "utilidad",
    descripcion: "Mide la velocidad en tiempo real usando el GPS de tu dispositivo. Ideal para viajes y deportes.",
    icono: "icons/Icovel.png",
    tamaño: "5.02 MB",
    fecha: "2026-08-09",
    link: "apks/Velocimetro.apk",
    screenshots: ["screenshots/Vel1.jpg", "screenshots/Vel2.jpg"]
  },
  {
    id: 6,
    nombre: "IPV (Mis Ventas)",
    version: "2.0",
    categoria: "utilidad",
    descripcion: "Gestiona el inventario de tus productos y controla tus ventas de forma sencilla. Registra productos con su precio, unidades, entradas y salidas. IPV calcula automáticamente el stock final, las unidades vendidas y el importe total. Ideal para pequeños negocios y emprendedores que necesitan un control eficiente de sus ventas diarias.",
    icono: "icons/IcoIPV.png",
    tamaño: "5.02 MB",
    fecha: "2026-08-18",
    link: "apks/IPV.apk",
    screenshots: ["screenshots/IPV0.gif", "screenshots/IPV1.jpg", "screenshots/IPV2.jpg", "screenshots/IPV3.jpg", "screenshots/IPV4.jpg", "screenshots/IPV5.jpg"]
  },
  {
    id: 7,
    nombre: "Bloc de Notas",
    version: "1.0",
    categoria: "utilidad",
    descripcion: ".",
    icono: "icons/Icoblog.png",
    tamaño: "0 MB",
    fecha: ".",
    link: "apks/",
    screenshots: ["screenshots/0.jpg", "screenshots/0.jpg"],
    enDesarrollo: true
  },
  {
    id: 8,
    nombre: "Vault",
    version: "1.0",
    categoria: "utilidad",
    descripcion: ".",
    icono: "icons/Icovault.png",
    tamaño: "0 MB",
    fecha: ".",
    link: "apks/",
    screenshots: ["screenshots/0.jpg", "screenshots/0.jpg"],
    enDesarrollo: true
  },
  {
    id: 9,
    nombre: "EtecsaCodes",
    version: "1.3",
    categoria: "utilidad",
    descripcion: "Aplicación diseñada para acceder rápida y fácilmente a los códigos USSD de ETECSA en Cuba.",
    icono: "icons/IcoEtecsaCodes.png",
    tamaño: "4.43 MB",
    fecha: "2026/08/15",
    link: "apks/EtecsaCodes.apk",
    screenshots: ["screenshots/EtecsaCodes0.gif", "screenshots/EtecsaCodes2.jpg", "screenshots/EtecsaCodes1.jpg"]
  },
  {
    id: 10,
    nombre: "Mi Almacen",
    version: "1.0",
    categoria: "utilidad",
    descripcion: "Proximamente...",
    icono: "icons/",
    tamaño: "0 MB",
    fecha: "",
    link: "apks/",
    screenshots: ["screenshots/.gif", "screenshots/.jpg", "screenshots/.jpg"],
    enDesarrollo: true
  },
  {
    id: 11,
    nombre: "Mis Entregas",
    version: "1.0",
    categoria: "utilidad",
    descripcion: "Mis Entregas es una herramienta práctica y sencilla para gestionar tus pedidos y entregas diarias de manera eficiente. Ideal para repartidores, vendedores, comerciantes y cualquier persona que necesite llevar un control de productos y clientes.",
    icono: "icons/IcoMEntregas.png",
    tamaño: "4.28 MB",
    fecha: "",
    link: "apks/Mis_Entregas.apk",
    screenshots: ["screenshots/MEntregas0.gif", "screenshots/MEntregas1.jpg"]
  }
];

// Si necesitas exportar para algún entorno, pero para web global es suficiente
if (typeof module !== 'undefined' && module.exports) {
  module.exports = APPS_DB;
}