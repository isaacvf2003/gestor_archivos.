
Esta version no usa backend ni base de datos. Todo corre en el navegador:

- Admin: admin / admin123
- Usuario: roman123 / roman123
- Los archivos se previsualizan con URL.createObjectURL.
- Nada se sube a Vercel.
- Al cerrar la pestana se pierden los archivos y datos creados.

Para publicar en Vercel:

1. Sube el repo a GitHub.
2. En Vercel crea un proyecto nuevo.
3. Elige este repositorio.
4. En Root Directory elige: demo
5. Build Command: dejar vacio
6. Output Directory: dejar vacio
7. Deploy
