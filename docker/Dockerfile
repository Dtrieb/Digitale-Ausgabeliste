# Nutzen eines kombinierten Nginx + PHP-FPM Images auf Alpin-Basis
FROM richarvey/nginx-php-fpm:latest

# Arbeitsverzeichnis im Container definieren (Standard für dieses Image)
WORKDIR /var/www/html

# Kopiere den Inhalt des src-Ordners in das Web-Verzeichnis des Containers
# Wichtig: Der Kontext beim Builden muss das Hauptverzeichnis sein!
COPY src/ .

# Exponiere Port 80 für den Webserver
EXPOSE 80

# Der Standard-Startbefehl ist bereits im Basis-Image definiert
