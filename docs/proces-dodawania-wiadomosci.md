# Proces Dodawania Wiadomości - Krok po Kroku

## Przegląd ogólny
Użytkownik wysyła wiadomość do grupy → System sprawdza uprawnienia → Zapisuje w bazie → Wysyła notyfikacje push do członków grupy → Zwraca utworzoną wiadomość.

---

## KROK 1: Request od klienta
Klient (aplikacja mobilna) wysyła HTTP POST request na endpoint `/api/messages`:

**Co zawiera:**
- **Header:** JWT token (Bearer token) - identyfikuje zalogowanego użytkownika
- **Body JSON:**
  - `group` - ID grupy, do której wysyłana jest wiadomość (np. 1)
  - `content` - treść wiadomości (np. "Hello world")

**Przykład:**
```http
POST /api/messages HTTP/1.1
Host: localhost:8000
Authorization: Bearer eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...
Content-Type: application/json

{
  "group": 1,
  "content": "Hello world"
}
```

---

## KROK 2: Symfony Security Layer (automatyczne)
Zanim request trafi do kontrolera, Symfony sprawdza bezpieczeństwo:

1. **Firewall `api`** przechwytuje request do `/api/messages`
2. **JWT Handler** sprawdza czy token jest:
   - Obecny w headerze
   - Prawidłowy (poprawny podpis)
   - Nie wygasł (exp timestamp)
3. **User Provider** ładuje użytkownika z bazy na podstawie email z tokenu
4. Jeśli wszystko OK → request idzie dalej do kontrolera
5. Jeśli błąd → zwraca 401 Unauthorized

**Konfiguracja:** `config/packages/security.yaml`

---

## KROK 3: Kontroler - Walidacja podstawowa
`MessageController::create()` otrzymuje request:

### 3.1 Pobranie zalogowanego użytkownika
- Z Security service pobiera obiekt User (już załadowany przez JWT)
- `$user = $this->security->getUser();`

### 3.2 Parsowanie danych z requesta
- Dekoduje JSON body
- Wyciąga `group` (ID grupy) i `content` (treść)

### 3.3 Walidacja danych wejściowych
Sprawdza czy:
- **content** nie jest pusty ani nie zawiera tylko białych znaków
- **content** nie przekracza 1000 znaków (max długość)
- **group** został podany

Jeśli coś jest nie tak → zwraca **400 Bad Request** z opisem błędu

**Plik:** `src/Controller/Api/MessageController.php`

---

## KROK 4: Weryfikacja grupy i członkostwa

### 4.1 Sprawdzenie czy grupa istnieje
- Pobiera obiekt Group z bazy po ID
- Używa `GroupRepository->find($groupId)`
- Jeśli nie znaleziono → zwraca **404 Not Found**

### 4.2 Weryfikacja członkostwa
- Sprawdza czy zalogowany użytkownik jest członkiem tej grupy
- Używa metody `$group->getUsers()->contains($user)`
- Jeśli użytkownik NIE jest członkiem → zwraca **403 Forbidden** ("You are not a member of this group")

**To kluczowy krok bezpieczeństwa** - zapobiega wysyłaniu wiadomości do grup, do których użytkownik nie należy!

---

## KROK 5: Utworzenie wiadomości

### 5.1 Instancja nowego obiektu Message
- Tworzy nowy obiekt encji `Message`
- `$message = new Message();`

### 5.2 Ustawienie pól
- **user** → przypisuje zalogowanego użytkownika (nadawca)
- **group** → przypisuje grupę
- **content** → treść wiadomości (po `trim()` - usunięcie białych znaków na końcach)

```php
$message->setUser($user);
$message->setGroup($group);
$message->setContent(trim($content));
```

### 5.3 Automatyczne timestampy
- **createdAt** i **updatedAt** są ustawiane automatycznie przez `TimestampableEntity` trait (Gedmo)
- Dzieje się to podczas flush() przez Doctrine lifecycle events

**Plik:** `src/Entity/Message.php`

---

## KROK 6: Zapis do bazy danych

### 6.1 Persist
- `EntityManager->persist($message)` - oznacza obiekt do zapisu
- **UWAGA:** Na tym etapie nic jeszcze nie jest w bazie!
- Obiekt jest tylko w Unit of Work Doctrine

### 6.2 Flush
- `EntityManager->flush()` - faktyczne zapisanie do bazy danych
- Wykonuje INSERT do tabeli `message`:

```sql
INSERT INTO message (user_id, group_id, content, created_at, updated_at)
VALUES (5, 1, 'Hello world', '2026-01-08 03:45:00', '2026-01-08 03:45:00')
RETURNING id
```

- Po flush, `$message->getId()` zwraca nadane przez bazę ID (np. 123)
- Doctrine automatycznie wypełnia ID w obiekcie

---

## KROK 7: Wysyłanie notyfikacji Firebase (asynchroniczne)

Wywołanie `MessageNotificationService->sendGroupMessageNotification()` w bloku try-catch:

### 7.1 Znalezienie tokenów urządzeń członków grupy
Service wykonuje zapytanie do bazy:

```sql
SELECT device_token.*
FROM device_token
JOIN "user" ON device_token.user_id = "user".id
JOIN user_group ON "user".id = user_group.user_id
WHERE user_group.group_id = 1
  AND "user".id != 5  -- wyklucza nadawcę
```

Pobiera listę wszystkich tokenów FCM członków grupy (oprócz nadawcy)

**Dlaczego bez nadawcy?**
Użytkownik nie potrzebuje notyfikacji o wiadomości, którą sam wysłał!

### 7.2 Inicjalizacja Firebase Messaging
- Ładuje credentials z pliku `config/firebase/firebase-credentials.json`
- Tworzy obiekt `Messaging` z Firebase SDK (Kreait)
- `$messaging = (new Factory())->withServiceAccount($path)->createMessaging();`

### 7.3 Przygotowanie treści notyfikacji
- **Tytuł:** "New message in [Nazwa grupy]"
- **Body:** "[Username nadawcy]: [Pierwsze 50 znaków wiadomości]..."

Przykład:
```
Tytuł: "New message in My Friends"
Body: "JohnDoe: Hello world"
```

### 7.4 Wysyłka do każdego tokena (w pętli)
Dla każdego tokena FCM:

1. Tworzy `CloudMessage` z targetem (token urządzenia)
2. Dodaje `Notification` (tytuł + body)
3. Wysyła przez Firebase SDK: `$messaging->send($message)`

```php
foreach ($tokens as $deviceToken) {
    try {
        $message = CloudMessage::withTarget('token', $deviceToken->getToken())
            ->withNotification(Notification::create($title, $body));
        $messaging->send($message);
    } catch (NotFound | InvalidArgument $e) {
        // Token nieprawidłowy - usuń z bazy
        $this->em->remove($deviceToken);
    } catch (\Throwable $e) {
        // Inny błąd - loguj i kontynuuj
    }
}
```

**Obsługa błędów:**
- Jeśli token jest nieprawidłowy (`NotFound` lub `InvalidArgument`) → usuwa go z bazy (stary/nieaktywny)
- Inne błędy → loguje (TODO) i kontynuuje dla następnych tokenów
- **Ważne:** Błędy notyfikacji NIE przerywają całego procesu!

### 7.5 Flush usunięć
- Jeśli jakieś tokeny były nieprawidłowe i zostały usunięte, zapisuje to w bazie
- `$this->em->flush();`

**Dlaczego try-catch?**
Jeśli Firebase ma problemy (brak internetu, błąd konfiguracji), wiadomość i tak jest zapisana w bazie i zwrócona użytkownikowi. Notyfikacje są "best effort" - nie blokują głównej funkcjonalności.

**Plik:** `src/Service/MessageNotificationService.php`

---

## KROK 8: Mapowanie na DTO (Data Transfer Object)

Kontroler tworzy obiekt `MessageDTO` do wysłania w response:

**Przepisuje dane z encji Message:**
- `id` → ID wiadomości z bazy
- `userId` → ID nadawcy
- `username` → Nazwa użytkownika nadawcy
- `userAvatar` → URL avatara nadawcy
- `groupId` → ID grupy
- `content` → Treść wiadomości
- `createdAt` → Timestamp w formacie 'Y-m-d H:i:s'
- `updatedAt` → Timestamp w formacie 'Y-m-d H:i:s'

```php
$dto = new MessageDTO();
$dto->id = $message->getId();
$dto->userId = $message->getUser()->getId();
$dto->username = $message->getUser()->getUsername();
$dto->userAvatar = $message->getUser()->getAvatar();
$dto->groupId = $message->getGroup()->getId();
$dto->content = $message->getContent();
$dto->createdAt = $message->getCreatedAt()->format('Y-m-d H:i:s');
$dto->updatedAt = $message->getUpdatedAt()->format('Y-m-d H:i:s');
```

**Dlaczego DTO a nie bezpośrednio encja?**
- Kontrola nad tym co wysyłamy (nie wszystkie pola encji)
- Unikamy circular references (User → Groups → Users → ...)
- Możemy formatować dane (np. daty jako stringi)
- Bezpieczeństwo - nie wystawiamy hasła, tokenów itp.

**Plik:** `src/Dto/MessageDTO.php`

---

## KROK 9: Response do klienta

Kontroler zwraca JSON response:

**Status HTTP:** `201 Created` (zasób został utworzony)

**Body JSON:**
```json
{
  "id": 123,
  "userId": 5,
  "username": "JohnDoe",
  "userAvatar": "https://example.com/avatar.jpg",
  "groupId": 1,
  "content": "Hello world",
  "createdAt": "2026-01-08 03:45:00",
  "updatedAt": "2026-01-08 03:45:00"
}
```

Klient dostaje pełne informacje o utworzonej wiadomości i może ją wyświetlić w UI.

```php
return $this->json($dto, Response::HTTP_CREATED);
```

---

## KROK 10: Notyfikacje push docierają do urządzeń (asynchronicznie)

**Równolegle** (niezależnie od response do klienta):

1. **Firebase Cloud Messaging** przetwarza wysłane notyfikacje
2. Przekazuje je do odpowiednich providerów:
   - **APNs** (Apple Push Notification service) dla iOS
   - **FCM** (Firebase Cloud Messaging) dla Android
3. Urządzenia członków grupy otrzymują notyfikację:
   - Pojawia się w obszarze powiadomień (notification tray)
   - Może wywołać dźwięk/wibrację (zależy od ustawień użytkownika)
   - Zawiera tytuł i treść wiadomości
4. Użytkownik może kliknąć notyfikację → aplikacja otwiera czat grupowy

---

## Podsumowanie timeline'u

```
0ms    - Klient wysyła POST /api/messages
1ms    - JWT verification (Symfony Security)
5ms    - Walidacja danych (content, group)
10ms   - Sprawdzenie członkostwa w grupie
15ms   - Utworzenie obiektu Message
20ms   - INSERT do bazy danych (flush)
25ms   - Rozpoczęcie wysyłki notyfikacji
        ├─ Query tokenów FCM (5ms)
        ├─ Inicjalizacja Firebase (10ms)
        └─ Wysyłka do N urządzeń (N×20ms)
50ms   - Mapowanie na DTO
55ms   - Response 201 Created do klienta

// Równolegle (asynchronicznie):
100ms  - Firebase przetwarza notyfikacje
200ms  - APNs/FCM przekazuje do urządzeń
500ms  - Użytkownicy dostają powiadomienia na telefony
```

**Całkowity czas odpowiedzi dla klienta:** ~50-60ms
**Czas dotarcia notyfikacji:** ~500ms-2s (zależy od sieci)

---

## Możliwe błędy i ich obsługa

| Sytuacja | HTTP Status | Kiedy? | Response |
|----------|-------------|--------|----------|
| Brak JWT tokenu | 401 Unauthorized | Security layer (automatycznie) | `{"message": "JWT Token not found"}` |
| Nieprawidłowy token | 401 Unauthorized | Security layer (automatycznie) | `{"message": "Invalid JWT Token"}` |
| Token wygasł | 401 Unauthorized | Security layer (automatycznie) | `{"message": "Expired JWT Token"}` |
| Brak content | 400 Bad Request | Krok 3.3 - walidacja | `{"error": "content is required and cannot be empty"}` |
| Content > 1000 znaków | 400 Bad Request | Krok 3.3 - walidacja | `{"error": "content cannot exceed 1000 characters"}` |
| Brak group | 400 Bad Request | Krok 3.3 - walidacja | `{"error": "group is required"}` |
| Grupa nie istnieje | 404 Not Found | Krok 4.1 | `{"error": "Group not found"}` |
| Nie jesteś członkiem | 403 Forbidden | Krok 4.2 | `{"error": "You are not a member of this group"}` |
| Błąd bazy danych | 500 Internal Error | Krok 6.2 (rzadkie) | `{"error": "Database error"}` |
| Błąd Firebase | (nie blokuje) | Krok 7 - log error, kontynuuj | Wiadomość zapisana, notyfikacje mogą nie dotrzeć |

---

## Optymalizacje w implementacji

### 1. Eager loading w Repository
W `MessageRepository->findGroupMessages()`:
```php
->addSelect('u', 'g')  // Ładuje User i Group razem z Message
->join('m.user', 'u')
->join('m.group', 'g')
```

**Korzyść:** Zapobiega N+1 problem - zamiast 1 query + N queries dla każdego usera/grupy, mamy tylko 1 query.

### 2. Index w bazie danych
Composite index `(group_id, created_at)` na tabeli `message`:
```sql
CREATE INDEX IDX_message_group_created ON message (group_id, created_at);
```

**Korzyść:** Przyspiesza query przy pobieraniu wiadomości - baza może szybko znaleźć wszystkie wiadomości dla grupy i posortować po dacie.

### 3. Try-catch dla Firebase
Notyfikacje w bloku try-catch:
```php
try {
    $this->notificationService->sendGroupMessageNotification(...);
} catch (\Throwable $e) {
    // Log error, ale kontynuuj
}
```

**Korzyść:** Problemy z notyfikacjami (Firebase down, błąd konfiguracji) nie blokują zapisania wiadomości.

### 4. Automatyczne czyszczenie tokenów
W pętli wysyłania notyfikacji:
```php
catch (NotFound | InvalidArgument $e) {
    $this->em->remove($deviceToken);
}
```

**Korzyść:** Nieprawidłowe tokeny FCM (odinstalowana aplikacja, wylogowany użytkownik) są automatycznie usuwane z bazy.

### 5. Nullable user_id w device_token
Kolumna `user_id` w tabeli `device_token` jest nullable:
```sql
ALTER TABLE device_token ADD user_id INT DEFAULT NULL
```

**Korzyść:** Pozwala na stopniową migrację starych tokenów bez `user_id`. Przy następnym wywołaniu `/api/save-token` przez użytkownika, token zostanie zaktualizowany.

### 6. Walidacja na poziomie encji
Assert constraints w `Message` entity:
```php
#[Assert\NotBlank]
#[Assert\Length(max: 1000)]
private string $content;
```

**Korzyść:** Walidacja działa również gdy Message jest tworzony w innych miejscach (nie tylko w kontrolerze), zapewnia spójność danych.

---

## Bezpieczeństwo

### Warstwa 1: JWT Authentication
- Każdy request musi zawierać prawidłowy JWT token
- Token jest weryfikowany przez Symfony Security
- User jest ładowany z bazy na podstawie tokenu

### Warstwa 2: Walidacja członkostwa w grupie
- Sprawdzenie `$group->getUsers()->contains($user)`
- Zapobiega wysyłaniu wiadomości do grup, do których użytkownik nie należy

### Warstwa 3: Walidacja danych
- Content nie może być pusty
- Content max 1000 znaków
- Zapobiega spamowi i nadużyciom

### Warstwa 4: Ownership na edycji/usuwaniu
- Tylko autor może edytować/usuwać swoje wiadomości (PATCH/DELETE endpointy)
- Sprawdzenie `$message->getUser()->getId() === $user->getId()`

---

## Pliki zaangażowane w proces

| Plik | Rola |
|------|------|
| `config/packages/security.yaml` | Konfiguracja JWT authentication, firewalls |
| `config/routes.yaml` | Rejestracja route'ów (automatycznie przez attributes) |
| `src/Controller/Api/MessageController.php` | Główny kontroler - obsługa requestu, walidacja, response |
| `src/Entity/Message.php` | Encja wiadomości - mapowanie ORM, walidacja |
| `src/Entity/User.php` | Encja użytkownika - nadawca wiadomości |
| `src/Entity/Group.php` | Encja grupy - weryfikacja członkostwa |
| `src/Entity/DeviceToken.php` | Encja tokenu FCM - notyfikacje push |
| `src/Repository/MessageRepository.php` | Custom query do pobierania wiadomości (eager loading) |
| `src/Repository/GroupRepository.php` | Repository grupy - find() |
| `src/Dto/MessageDTO.php` | Data Transfer Object - format response |
| `src/Service/MessageNotificationService.php` | Service do wysyłania notyfikacji Firebase |
| `migrations/Version20260108023539.php` | Migracja bazy - dodanie user_id do device_token, index |

---

## Diagram przepływu danych

```
┌─────────────┐
│   Klient    │
│  (Mobile)   │
└──────┬──────┘
       │ POST /api/messages
       │ {group: 1, content: "Hi"}
       │ Authorization: Bearer <JWT>
       ▼
┌──────────────────────────┐
│  Symfony Security Layer  │
│  - JWT Verification      │
│  - Load User from DB     │
└──────┬───────────────────┘
       │ $user object
       ▼
┌──────────────────────────┐
│  MessageController       │
│  ::create()              │
│  - Parse request         │
│  - Validate data         │
│  - Check membership      │
└──────┬───────────────────┘
       │
       ▼
┌──────────────────────────┐
│  EntityManager           │
│  - new Message()         │
│  - persist()             │
│  - flush()               │
└──────┬───────────────────┘
       │
       ├──────────────────────────────┐
       │                              │
       ▼                              ▼
┌─────────────────┐    ┌──────────────────────────────┐
│   PostgreSQL    │    │ MessageNotificationService   │
│   INSERT INTO   │    │ - Find FCM tokens            │
│   message       │    │ - Init Firebase SDK          │
└─────────────────┘    │ - Send notifications         │
                       └──────┬───────────────────────┘
                              │
                              ▼
                       ┌─────────────────┐
                       │ Firebase Cloud  │
                       │   Messaging     │
                       └──────┬──────────┘
                              │
       ┌──────────────────────┴──────────────────────┐
       │                                             │
       ▼                                             ▼
┌──────────────┐                            ┌──────────────┐
│  APNs (iOS)  │                            │  FCM (And.)  │
└──────┬───────┘                            └──────┬───────┘
       │                                           │
       ▼                                           ▼
┌──────────────┐                            ┌──────────────┐
│  iPhone #1   │                            │  Android #2  │
│  (Member 1)  │                            │  (Member 2)  │
└──────────────┘                            └──────────────┘

┌──────────────────────────┐
│  Response to Client      │
│  201 Created             │
│  {id, content, ...}      │
└──────────────────────────┘
```

---

## FAQ - Najczęstsze pytania

### Q: Co się dzieje gdy Firebase jest niedostępny?
**A:** Wiadomość jest zapisana w bazie i zwrócona użytkownikowi. Notyfikacje po prostu nie dotrą. Try-catch zapobiega crashowi aplikacji.

### Q: Dlaczego notyfikacje są synchroniczne a nie przez message queue?
**A:** To MVP (Minimum Viable Product). Firebase SDK jest szybki (~100-200ms dla kilku tokenów). W przyszłości można użyć Symfony Messenger dla asynchroniczności.

### Q: Co się dzieje z tokenami FCM po odinstalowaniu aplikacji?
**A:** Firebase zwróci błąd `NotFound`, a service automatycznie usunie taki token z bazy podczas kolejnej wysyłki.

### Q: Czy można wysłać wiadomość do wielu grup jednocześnie?
**A:** Nie, endpoint przyjmuje tylko jedno `group`. Klient musi wywołać POST dla każdej grupy osobno.

### Q: Jak działa paginacja wiadomości?
**A:** Endpoint GET przyjmuje `offset` i `limit`. Przykład: `?group_id=1&offset=0&limit=50` dla pierwszych 50, potem `offset=50&limit=50` dla kolejnych.

### Q: Czy można edytować cudzą wiadomość?
**A:** Nie. Endpoint PATCH sprawdza `$message->getUser()->getId() === $user->getId()`. Tylko autor może edytować.

### Q: Jak długo trwa dotarcie notyfikacji?
**A:** Zazwyczaj 500ms-2s, ale zależy od:
- Połączenia internetowego urządzenia
- Obciążenia Firebase/APNs/FCM
- Czy urządzenie jest w trybie uśpienia (doze mode na Android)

### Q: Co się dzieje przy bardzo długiej wiadomości (>1000 znaków)?
**A:** Walidacja zwróci `400 Bad Request` z komunikatem "content cannot exceed 1000 characters". Wiadomość nie zostanie zapisana.

### Q: Czy notyfikacja zawiera pełną treść wiadomości?
**A:** Nie. Body notyfikacji zawiera tylko pierwsze 50 znaków + "..." jeśli jest dłuższa. Pełna treść jest pobierana gdy użytkownik otworzy aplikację.

---

## Rozszerzenia możliwe w przyszłości

1. **Asynchroniczna wysyłka notyfikacji**
   - Użycie Symfony Messenger
   - Message queue (RabbitMQ, Redis)
   - Background worker

2. **Read receipts (potwierdzenia odczytu)**
   - Nowa tabela `message_read` (user_id, message_id, read_at)
   - Endpoint POST /api/messages/{id}/mark-read

3. **Reakcje na wiadomości (emoji)**
   - Tabela `message_reaction` (user_id, message_id, emoji)
   - Endpoint POST /api/messages/{id}/react

4. **Wiadomości głosowe/obrazy**
   - Upload plików do storage (S3, local)
   - Pole `attachment_url` w Message
   - Walidacja typu pliku

5. **Wyszukiwanie wiadomości**
   - Full-text search (PostgreSQL `ts_vector`)
   - Endpoint GET /api/messages/search?q=query

6. **Typing indicators**
   - WebSocket/Server-Sent Events
   - Broadcast "[User] is typing..."

7. **Rate limiting**
   - Symfony RateLimiter bundle
   - Max N wiadomości na minutę per user

8. **Soft delete**
   - Gedmo SoftDeleteable
   - Pole `deleted_at` zamiast hard delete

9. **Edycja - historia zmian**
   - Tabela `message_edit_history`
   - Pokazywanie "(edited)" w UI

10. **Notyfikacje grupowe (batch)**
    - Firebase topic messaging
    - Subscribe/unsubscribe do topic grupy