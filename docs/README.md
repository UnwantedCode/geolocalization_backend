# Dokumentacja Projektu Geolocalization Backend

Folder zawierający szczegółową dokumentację techniczną projektu.

## Zawartość

### 📝 [Proces Dodawania Wiadomości](proces-dodawania-wiadomosci.md)
Szczegółowy opis krok po kroku procesu wysyłania wiadomości w czacie grupowym:
- Request od klienta
- JWT authentication
- Walidacja i weryfikacja członkostwa
- Zapis do bazy danych
- Wysyłanie notyfikacji Firebase
- Response do klienta
- Timeline i optymalizacje
- Obsługa błędów
- Diagram przepływu danych

## Przydatne informacje

### Endpointy API Czatu

| Method | Endpoint | Opis |
|--------|----------|------|
| GET | `/api/messages?group_id={id}&limit={n}&offset={n}` | Pobieranie wiadomości z grupy |
| POST | `/api/messages` | Wysyłanie nowej wiadomości |
| PATCH | `/api/messages/{id}` | Edycja własnej wiadomości |
| DELETE | `/api/messages/{id}` | Usuwanie własnej wiadomości |

### Kluczowe pliki projektu

- `src/Controller/Api/MessageController.php` - Główny kontroler czatu
- `src/Service/MessageNotificationService.php` - Service notyfikacji Firebase
- `src/Entity/Message.php` - Encja wiadomości
- `src/Repository/MessageRepository.php` - Custom queries
- `config/packages/security.yaml` - Konfiguracja JWT

### Technologie

- **Backend:** Symfony 6.4
- **Baza danych:** PostgreSQL (przez SSH tunnel)
- **Authentication:** JWT (LexikJWTAuthenticationBundle)
- **Push notifications:** Firebase Cloud Messaging (Kreait PHP SDK)
- **ORM:** Doctrine
- **Admin panel:** EasyAdmin

## Jak dodać nową dokumentację?

1. Utwórz plik `.md` w folderze `docs/`
2. Dodaj link do niego w tym README
3. Użyj Markdown dla formatowania
4. Dodaj przykłady kodu, diagramy i FAQ jeśli to możliwe

## Konwencje dokumentacji

- Używaj nagłówków hierarchicznie (H1 → H2 → H3)
- Dodawaj bloki kodu z językiem: \`\`\`php, \`\`\`sql, \`\`\`json
- Używaj tabel dla porównań i specyfikacji
- Dodawaj przykłady requestów/responses
- Wyjaśniaj "dlaczego" a nie tylko "jak"