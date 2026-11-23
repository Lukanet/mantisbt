# Търсене на тикети през REST (GET /api/rest/issues) и Sphinx - проверка и подобрения

Дата: 2026-11-22. Проверено на bt.darco.lan (Apache vhost `014-bt.conf`, `DocumentRoot /projects/mantisbt/` -
сервира точно този код). Заявките са с токена на Barsy потребител 43 (не е записан никъде).

## 1. Резултат по точки

| # | Недостатък | Проверка | Вярно ли е | Какво е направено |
|---|---|---|---|---|
| 1 | Подреждане само по една колона | `sort=status,last_updated&sort_direction=ASC,DESC` → **HTTP 500** (TypeError `explode(): Argument #2 must be of type string, array given`, core/filter_api.php:634 по стария код, извикано от rest_issue_build_inline_filter) | **Потвърден** | `sort`/`sort_direction` вече не се разделят на масив; приемат списък колони, посоките се сдвояват по позиция (липсваща посока = последната дадена). filter_ensure_valid_filter вече търпи масив и малки букви (`asc`). |
| 2 | Подреждане по date_submitted, handler_id, project_id … мълчаливо пада към last_updated DESC | `sort=date_submitted&sort_direction=ASC` връщаше 384748 (най-новия); `sort=bogus` - 200 без предупреждение. Причина: BugFilterQuery::build_order_by() → filter_get_visible_sort_properties_array() пази само колоните, видими на View Issues за потребителя | **Потвърден** | Нова опция на BugFilterQuery `sort_unrestricted` (inline филтрите я ползват): подрежда по всички валидни колони, видими или не, + `id DESC` за стабилно страниране. Непозната колона → **400** със списък на позволените. Псевдоними: `created_at`, `updated_at`, `project`, `category`, `handler`, `reporter`. Колони на допълнителни полета: `custom_<име>` (напр. `custom_Клиент`). |
| 3 | Непознат етикет в tag_string → всички тикети | `tag_string=NoSuchTagXYZ` → total 73778 (всички); `tag_select=999999` → също | **Потвърден** (и за tag_select) | Непознат етикет: в „някой от“ групата - ако няма друг познат → **празен резултат + warning**; с `+` (задължителен) → празен резултат + warning; с `-` → без ефект + warning; смесен с познати → игнорира се + warning. |
| 4 | Няма филтър по допълнителни полета | Ядрото поддържа `custom_fields` във филтъра, но REST не го излага; core пропуска мълчаливо поле без права → всички тикети | **Потвърден** | `custom_fields[<име или id>]=<стойност>` (точно), `custom_fields_contains[<име или id>]=<текст>` (частично, LIKE с екраниране на `%`/`_`), няколко стойности - `custom_fields[6][]=82&custom_fields[6][]=92` (OR), `[none]` - празно. Непознато поле → 400. Поле без право за четене / не е към проектите → празен резултат (не „всички“). Правата се проверяват per project (access_level_r), както в ядрото. |
| 5 | project_id - само един проект, без подпроекти | С филтър: project 12 → 3659, project 2 → 54419. **Без** филтър `project_id=12` → 71827 (стандартният филтър включва подпроекти) - непоследователно | **Потвърден** | `project_id=12,2` (списък; всеки трябва да съществува и да е достъпен, иначе 404 както досега), `include_subprojects=1` (добавя достъпните подпроекти). `project_id=12&include_subprojects=1&status_id=…` → 71827. |
| 6 | Датите само по дни | `created_after=2026-11-04T17:00:00` → 400 | **Потвърден** | `created_after/before`, `updated_after/before` приемат и `YYYY-MM-DDTHH:MM[:SS][Z\|±HH:MM]` (интервал вместо `T` също). Само дата - както досега (обратно съвместимо). Ако единият край има час, целият интервал става точен до секунда (опция `date_ranges`), долна граница-дата = 00:00:00, горна = 23:59:59. |
| 7 | Само page/page_size | `offset=3` → warning „Unknown query parameter 'offset'“ | **Потвърден** | `offset` (≥0) + `limit` (≥1, или `page_size`). Работи и без inline филтър и с `fields=id`. `page` се игнорира с warning, ако има `offset`. |
| 8 | Тежки бележки | `issues/1?select=id,notes` → 98 739 байта (79 бележки) | **Потвърден** | `notes_limit=N` - само последните N (и поле `notes_count` с общия брой), `notes_order=asc\|desc`. И за единичен тикет. `issues/1?select=id,notes&notes_limit=3&notes_order=desc` → 1 106 байта. |
| 9 | „Статусът е сменен на X в период“ | История: 1.23 млн реда, 212 хил. за status, индекс по date_modified и bug_id | Оценено - има смисъл, евтино | `status_changed_to=80,90`, `status_changed_after`, `status_changed_before` (дата или дата+час). Подзаявка `{bug}.id IN (SELECT bug_id FROM {bug_history} WHERE field_name='status' AND type=0 …)`; ограничено до проектите с `view_history_threshold`. С период: 0.1–0.8 s; без период: ~2.2 s. Брой съвпада с ръчна SQL заявка (1257 за 80 от 2026-01-01). |
| Sphinx | виж т.3 | код + тест със заместен клиент | частично вярно - има пропуски в достъпа | Доставчик за `/issues?search=` + поправки в маршрута на плъгина (виж т.3). |

## 2. Нови параметри на GET /api/rest/issues (всички са допълнителни)

| Параметър | Стойности | Пример |
|---|---|---|
| `sort` | списък колони; `id, project_id, reporter_id, handler_id, priority, severity, reproducibility, status, resolution, category_id, date_submitted, last_updated, os, os_build, platform, version, fixed_in_version, target_version, view_state, summary, due_date (ако е видим), custom_<име>`; псевдоними `created_at, updated_at, project, category, handler, reporter` | `sort=status,priority,updated_at` |
| `sort_direction` | `ASC`/`DESC` (без значение главни/малки), списък по позиция | `sort_direction=ASC,DESC,DESC` |
| `project_id` | id или списък | `project_id=12,2` |
| `include_subprojects` | `1/0/true/false/yes/no` | `project_id=12&include_subprojects=1` |
| `custom_fields[<име\|id>]` | точна стойност; `[]` за няколко; `[none]` | `custom_fields[КлиентID]=82` |
| `custom_fields_contains[<име\|id>]` | подниз | `custom_fields_contains[Клиент]=Хотел` |
| `created_after` … `updated_before` | `YYYY-MM-DD` или `YYYY-MM-DDTHH:MM[:SS][Z\|±HH:MM]` | `updated_after=2026-11-04T16:53:30%2B02:00` |
| `offset`, `limit` | цели ≥0 / ≥1 | `offset=3&limit=4` |
| `notes_limit`, `notes_order` | ≥0; `asc`/`desc` | `select=id,notes&notes_limit=3&notes_order=desc` |
| `status_changed_to`, `status_changed_after`, `status_changed_before` | id-та на статуси; дати | `status_changed_to=80&status_changed_after=2026-10-01` |
| `search_engine` | `auto` (подразбиране), `like`, `fulltext` | `search=грешка&search_engine=like` |

Нови полета в отговора: `search_engine` (`like` / `sphinx`) при `search`; `notes_count` в тикета при `notes_limit`.
Нови предупреждения (`warnings`): непознат етикет, повторена колона за подреждане, page игнориран при offset,
page_size заменен от limit, пълнотекстовото търсене не е използвано / отрязано, search_engine без search.

Бележка: подреждането по `handler_id`/`reporter_id` е по потребителско име (както в ядрото), по `project_id` -
по име на проекта, по `category_id` - по име на категорията.

## 3. Sphinx (BarsySupport)

### Какво има в кода
- Маршрут `GET /api/rest/plugins/BarsySupport/search` (BarsySupport.php, `rest_routes`) → `BarsySearchService::search()`
  (classes/SearchService.php). Уеб страницата `search_v2.php` и `autocomplete.php` имат **собствен** Sphinx код -
  не ползват SearchService, така че промените не ги засягат.
- Индекс `mantis_index` на localhost:9312, режим SPH_MATCH_EXTENDED2. **Конфигурацията на индекса (sphinx.conf) я
  няма в нито едно repo на тази машина** и демонът не е инсталиран тук - затова какво точно се индексира, колко
  често и с каква морфология **не може да се провери от кода**. Косвено: autocomplete филтрира по атрибут `project_id`
  и подрежда по атрибут `modified`, и търси с `*` (значи има min_prefix_len/infix). Трябва да се вземат от
  продукцията: `/etc/sphinxsearch/sphinx.conf` (секциите за mantis_index) и crontab-а с `indexer`.
- Морфология за български: Sphinx 2.x няма български стемер (има stem_en, stem_ru, stem_enru; libstemmer също няма
  български). Другите индекси на Barsy ползват `morphology = stem_en, stem_ru` + `wordforms_bg.txt`.

### Недостатъци на маршрута (намерени)
1. **Частни тикети**: проверяваше само достъпа до проекта - частни тикети (view_state=50) и „limited view“ се
   връщаха на хора без право. → Поправено: кандидатите от Sphinx минават през BugFilterQuery (`visible_issue_ids`).
2. **Частни бележки**: `last_note` и `notes_count` включваха частни бележки. → Поправено: същото правило като в ядрото
   (публични, собствени, или `private_bugnote_threshold` в проекта).
3. Ако индексът съдържа текста на частни бележки, тикет може да се намери по дума само от частна бележка. →
   Тикетите от проекти, където потребителят не вижда частни бележки, се махат от Sphinx съвпаденията (плъгин опция
   `sphinx_indexes_private_notes`, ON по подразбиране; OFF ако индексът не съдържа частни бележки).
4. `notes_count` се умножаваше по броя етикети (JOIN с bug_tag) → `COUNT(DISTINCT BN.ID)`.
5. Ограничение на резултатите: `SetLimits(0, 10000)` при max_matches по подразбиране 1000 → реално до 1000
   (отчита се в `sphinx.truncated`). Не е променено за маршрута.
6. Формата на отговора е различна от /issues (плосък обект: `status` е число, `handler` е име, няма `select`,
   `page_count`); филтрите са други имена (`tag_id`, `client_name` = точна стойност на поле 2, `client_id` = поле 6
   с (int) cast, `date_type` 1/2/3, `sort_by` 1/2/3). Не е преработен в пълен заместител - вместо това /issues
   може да ползва Sphinx (по-долу), и така получава всички филтри, `select`, страниране и проверки на /issues.

### /issues?search=… през Sphinx (направено)
- Ядро: ако е зададен `$g_rest_issue_search_provider` (callable), `search` се изпълнява от него и връща само
  кандидат id-та; всички проверки за достъп и филтри остават на BugFilterQuery (опция `id_in`).
- `search_engine=auto`: Sphinx ако е настроен и отговаря, иначе LIKE + warning. `fulltext`: 503 ако не отговаря,
  400 при грешен израз / не е настроен, 403 ако потребителят не вижда частни бележки. `like`: винаги LIKE.
- Защита на частните бележки: ако `$g_rest_issue_search_indexes_private_notes` (по подразбиране ON; плъгинът го
  взима от своята опция `sphinx_indexes_private_notes`) - Sphinx се ползва само за потребители, които виждат частни
  бележки във всички търсени проекти; останалите получават LIKE (+ warning).
- **Включено по подразбиране** (плъгин опция `rest_issues_fulltext` = ON). Изключване: `$g_plugin_BarsySupport_rest_issues_fulltext = OFF;`
  в config_inc.php или ред `plugin_BarsySupport_rest_issues_fulltext` = 0 в mantis_config_table. Без плъгина -
  `$g_rest_issue_search_provider = 'BarsySearchService::rest_issue_search';`.
- Адрес на searchd - плъгин опции (config_inc.php `$g_plugin_BarsySupport_<име>` или mantis_config_table
  `plugin_BarsySupport_<име>`): `sphinx_host` (localhost), `sphinx_port` (9312, протокол **SphinxAPI**, не SphinxQL/9306),
  `sphinx_index` (mantis_index), `sphinx_connect_timeout` (2 s), `sphinx_read_timeout` (5 s). Ползват ги и маршрутът
  на плъгина, и /issues. Увиснал тунел (приема връзката, не отговаря) → грешка след sphinx_read_timeout; при `auto`
  това е LIKE + warning, никога 503.
- Отличия от LIKE: Sphinx синтаксис (`"фраза"`, `-дума`, `дума*`), търси само в индексираните полета, цели думи
  (с нормализиране на словоформите), не подниз. Таван: 1000 съвпадения на заявка към searchd (виж „Таван и други
  филтри“).
- Проверено: на dev демонът не работи → `auto` връща LIKE с warning `connection to localhost:9312 failed`,
  `fulltext` → 503. Успешният път е проверен с фалшив доставчик (CLI), включително сечение с непознат етикет и
  ограничението за частни бележки.

### Тест с продукционния Sphinx

Индексът е от продукцията, базата на bt.darco.lan е по-старо копие. Какво става с разликите:
- id от индекса, които ги няма в dev базата (или са в изключен проект / невидими), **тихо отпадат**: Sphinx дава само
  кандидати, а BugFilterQuery брои и странира само съществуващите и видимите. `total_count`/`page_count` са верни
  за dev базата. Проверено с фалшив доставчик: кандидати [384748, 384747, 1, 999999999] → резултат 3 тикета, без грешка.
  Маршрутът на плъгина се държи по същия начин (`total`), но `sphinx.total_found` е броят в продукционния индекс.
- Тикети, създадени/променени в dev след копието, Sphinx не ги знае (или ги знае с друг текст); при търсене по номер
  или по нов текст ползвайте `search_engine=like`.
- searchd връща до 1000 съвпадения на заявка; как това се съчетава с филтрите - в следващия раздел.

1. Тунел (на dev машината; searchd на продукцията слуша SphinxAPI на 9312):
   ```bash
   ssh -N -L 127.0.0.1:9312:127.0.0.1:9312 <prod>
   ```
   (`sphinx_host` по подразбиране е `localhost`; ако се резолвира до ::1, а тунелът е само IPv4, задайте
   `$g_plugin_BarsySupport_sphinx_host = '127.0.0.1';`.)
2. Проверка, че отговаря: `nc -z -w2 127.0.0.1 9312 && echo open`, после
   `curl -s -H "Authorization: $T" 'http://bt.darco.lan/api/rest/plugins/BarsySupport/search?q=test&page_size=1'` -
   очаква се 200 с `"sphinx":{"total_found":…}` (не 503).
3. Заявки (`T` = API токен; `-G --data-urlencode` за кирилицата):
   ```bash
   B=http://bt.darco.lan/api/rest/issues
   # a) Sphinx срещу LIKE: и двете 200; "search_engine":"sphinx" / "like". Броевете се различават - Sphinx търси думи
   #    (и в индексираните полета), LIKE - подниз в заглавие/описание/бележки.
   curl -sG -H "Authorization: $T" $B --data-urlencode 'search=фискален' -d search_engine=fulltext -d count_only=1
   curl -sG -H "Authorization: $T" $B --data-urlencode 'search=фискален' -d search_engine=like -d count_only=1
   # b) Български словоформи: без български стемер се очаква различен брой за "принтер" и "принтера";
   #    "принтер*" (префикс) трябва да покрие и двете.
   for w in принтер принтера 'принтер*'; do curl -sG -H "Authorization: $T" $B --data-urlencode "search=$w" -d search_engine=fulltext -d count_only=1; echo; done
   # c) По подразбиране (auto) - Sphinx без warning; след спиране на тунела - "like" + warning, пак 200.
   curl -sG -H "Authorization: $T" $B --data-urlencode 'search=фискален' -d select=id,summary -d page_size=5
   # d) Частни бележки: с токен на потребител БЕЗ private_bugnote_threshold (DEVELOPER) в някой проект -
   #    auto: "search_engine":"like" + warning "only available to users who may see the private notes";
   #    fulltext: 403. С разработчик/администратор: "sphinx".
   curl -sG -H "Authorization: $T_REPORTER" $B --data-urlencode 'search=фискален' -d search_engine=fulltext -d count_only=1
   ```

**Резултат (2026-11-22, тунелът към продукционния searchd работи на 127.0.0.1:9312 и [::1]:9312):**
- Индексът (видян през API-то): полета `title, date_submitted, status, project_name, reporter, username, category_name,
  resolution, content, custom_fields, tags, source, subsource, uri`; атрибути `title, status, username, content,
  custom_fields, source, subsource, uri` (низове), `modified` (timestamp), `project_id`, `source_id`. 79 466 документа,
  всички с `source=bugzilla` (само тикети). `content` съдържа описанието **и бележките** (вероятно и частните) →
  защитата за частни бележки остава включена (`sphinx_indexes_private_notes` = ON).
- Бавно: понеже текстът е и атрибут, searchd го връщаше за всяко съвпадение (6218 съвпадения → 10–30 s, Apache
  отговаряше 503 след 60 s). Поправено с `SetSelect('project_id')` → Sphinx 0.06 s, цялата заявка ~0.35 s.
- a) `search=фискален`: fulltext → 5803 (`sphinx`, 0.35 s; Sphinx намира 6218, 415 липсват в dev базата или не са видими
  и тихо отпадат); like → 2678 (11 s).
- b) `принтер` / `принтера` / `принтери` → едни и същи 10 225 документа (думата се нормализира до „принтер“ -
  словоформите работят); `фискални` → като `фискален`. Над 10 000 съвпадения → warning „matched more issues than it can
  return“ и total 9488. `"фискален принтер"` (фраза) → 2974, без кавички → 4230.
- c) auto (подразбиране) → `sphinx` без warning; без демон → `like` + warning, 200.
- d) Потребител без `private_bugnote_threshold` (проверено в CLI с потребител 28): auto → `like` + warning, fulltext → 403;
  администратор → `sphinx`.
- Числа: `1107` → 165 документа (цели думи); `a12345` → 0 - частично съвпадение вътре в код няма (само префикс с `*`).
- Маршрутът `/plugins/BarsySupport/search?q=фискален` → 0.8 s, total 955 от `matches` 1000 (`truncated: true`) - той
  пита за най-много 1000 (max_matches по подразбиране), както преди.

### Таван и други филтри (поправено 2026-11-22)

Проблем: Sphinx връща най-добрите N по релевантност, а филтрите на Mantis (дати, отговорник, статус…) се прилагаха
след това → тикети, които отговарят на всичко, се изместваха от по-релевантни, отпаднали после. Пример:
`search=принтер&created_after=2026-01-01` - fulltext 217, like 161, 71 от тях липсваха във fulltext.

Решение (core issues_rest.php + SearchService):
- Таван 1000 на заявка към searchd (`MAX_MATCHES`) и в маршрута, и в /issues.
- Атрибутите на индекса (status, project_id, modified) НЕ се ползват за филтриране - остаряват между преиндексиранията.
  Базата остава единственият източник за филтрите.
- Преди Sphinx: филтърът на Mantis **без** search дава id-тата на кандидатите (`rest_issue_search_candidates`,
  QUERY_TYPE_DISTINCT_IDS - всички права и филтри). Ако са до `$g_rest_issue_search_max_candidates` (по подразбиране
  131 072 = 32 × 4096), доставчикът ги получава като `candidate_ids`.
- В Sphinx: документ id = bug id (проверено, напр. 57186). Кандидатите се подават като `SetFilter('@id', …)` на части
  по 4096 (лимит на searchd 2.x: „set length … should be in 0..4096“) в пакет до 32 заявки (`AddQuery`/`RunQueries`).
  Ако част има повече от 1000 съвпадения, тя се дели според `total_found` и се пита отново (общо до 30 000 съвпадения).
  Резултатът е обединението; таванът се прилага върху вече филтрираното множество.
- Ако пак е над тавана (много честа дума или над 131 072 кандидата): `auto` → LIKE + warning; `fulltext` → най-добрите
  1000 на част + warning „The result is incomplete“.
- Подредбата е тази от `sort` (както преди); релевантността не участва в /issues, затова не се влошава.

Измерено (през тунела, dev база 74 570 тикета, индекс 79 466 документа):

| Заявка | fulltext | like | auto |
|---|---|---|---|
| `принтер` + `created_after=2026-01-01` | 251 (1.4 s; 0.5 s при топъл кеш) | 161 (1.3–2.9 s) | sphinx, 0.8 s |
| `принтер` + `handler_id=166` | 246 (0.6 s) | 237 (1.4 s) | sphinx, 0.6 s |
| `принтер` + `hide_status_id=90` (22 389 кандидата) | 2277 (0.9 s) | 1755 (12.7 s) | sphinx, 1.3 s |
| `принтер` без филтри (73 778 кандидата) | 9629 | 8914 (11 s) | sphinx, 1.3 s |
| `фискален принтер` без филтри | 4230 | - | sphinx, 0.9 s |
| `клиент` без филтри (65 хил. съвпадения) | непълен + warning | 42 857 | like + warning, 10–18 s |

Разлики между like и fulltext (проверени поотделно чрез атрибута `content` на документа в индекса):
- всички тикети от like, които липсват във fulltext (38 / 51 / 239 в трите случая), са такива, при които текстът в
  **индекса** няма думата: dev базата е по-старо копие и се е разминала с продукцията (напр. 376506: бележка 1276406 в dev
  е „От Давид: … този принтер не е ъпдейтнат“, а в индекса под това id е друг текст; тикетът е в индекса, но без думата);
- в hide_status_id=90 още 3 са подниз: „принтерче“, „термопринтер“, „принтерa“ (латинско a) - LIKE търси подниз, Sphinx думи;
- обратно, тикетите само във fulltext имат в индекса „принтер/принтера/принтери/принтерите/принтерът“, а в dev базата не.
Т.е. на продукция (индекс и база от един и същи източник) тикети не би трябвало да липсват освен заради подниз и
забавяне на преиндексирането.

### Какво да се пусне на dev за реален Sphinx тест (не е пускано)
```bash
apt-get install sphinxsearch            # Debian 12: 2.2.11-8+b1
```
`/etc/sphinxsearch/sphinx.conf` - най-добре копие на продукционния; ако го няма, предложение:
```
source mantis_src
{
  type = mysql
  sql_host = localhost
  sql_user = bt
  sql_pass = <$g_db_password от config/config_inc.php>
  sql_db = bt_lukanet_com
  sql_query_pre = SET NAMES utf8mb4
  sql_query = SELECT b.id, b.summary, t.description, t.steps_to_reproduce, t.additional_information, \
      (SELECT GROUP_CONCAT(nt.note SEPARATOR ' ') FROM mantis_bugnote_table n \
         JOIN mantis_bugnote_text_table nt ON nt.id = n.bugnote_text_id WHERE n.bug_id = b.id) AS notes, \
      (SELECT GROUP_CONCAT(cf.value SEPARATOR ' ') FROM mantis_custom_field_string_table cf WHERE cf.bug_id = b.id) AS custom_fields, \
      b.project_id, b.status, b.last_updated AS modified \
    FROM mantis_bug_table b JOIN mantis_bug_text_table t ON t.id = b.bug_text_id
  sql_attr_uint = project_id
  sql_attr_uint = status
  sql_attr_timestamp = modified
}
index mantis_index
{
  source = mantis_src
  path = /var/lib/sphinxsearch/data/mantis_index
  morphology = stem_enru
  min_word_len = 1
  min_infix_len = 3          # частично съвпадение на числа и кодове (a123456, 0x1F…)
  index_exact_words = 1
  html_strip = 1
}
indexer { mem_limit = 256M }
searchd
{
  listen = 127.0.0.1:9312
  log = /var/log/sphinxsearch/searchd.log
  query_log = /var/log/sphinxsearch/query.log
  pid_file = /var/run/sphinxsearch/searchd.pid
}
```
(По-горе индексът включва и частни бележки → оставете `sphinx_indexes_private_notes` ON. За да ги изключите,
добавете `AND n.view_state = 10` в подзаявката за бележки и сложете опцията на OFF.)
```bash
indexer --all                          # първо индексиране (≈74 хил. тикета, 259 хил. бележки / 115 MB)
systemctl enable --now sphinxsearch    # или: searchd --config /etc/sphinxsearch/sphinx.conf
# свежест: напр. cron */5 * * * * indexer --rotate mantis_index (или main+delta по last_updated)
```
После включете доставчика (по-горе) и проверете:
`GET /api/rest/issues?search=<дума>&search_engine=fulltext&select=id` → `"search_engine":"sphinx"`, и
`GET /api/rest/plugins/BarsySupport/search?q=<дума>`.

## 4. Какво да се промени в Barsy (MantisApiClient / Mantis_getlist)

- `getList()`: вместо двете страници (`page`, `page+1` и `array_slice`) - една заявка с `offset=$offset&limit=$length`.
  `has_more` = `offset + count(issues) < total_count`.
- `MantisListOrderByData::mantisSort()`: махнете „само по една колона“; подайте `sort=a,b` и `sort_direction=ASC,DESC`.
  `created_at` вече може да е `date_submitted` (или `created_at`) вместо `id`. Нови колони: `handler`, `reporter`,
  `project`, `resolution`, `due_date`, `view_state`, `custom_<име>`.
- Филтри: `project_id` да приема списък + флаг `include_subprojects` (за 'Barsy общ' (12) почти винаги е нужен);
  `custom_fields[Клиент]`, `custom_fields[КлиентID]`, `custom_fields[Лиценз]`, `custom_fields[Сделка]`,
  `custom_fields[Server]`, `custom_fields[ErrorSignature]`, `custom_fields[Commit ID]` (+ `custom_fields_contains[...]`);
  `status_changed_to` + `status_changed_after/before`; датите да пращат и час (`MantisFilter_datePeriod` вече
  приема „2026-09-01 10:00:00“ - да се подава като `2026-09-01T10:00:00`).
- Бележки: при `extra_properties.notes` подайте `notes_limit` (напр. 5) и `notes_order=desc`; `notes_count` е общият брой.
- Обработете `warnings` от отговора (непознат етикет, отрязано пълнотекстово търсене и др.) - покажете ги на модела.
- 404 при списък от проекти: съобщението съдържа id-то на липсващия проект (`Project 'X' doesn't exist`).
- Ако търсенето трябва да е точен подниз (кодове, номера) - `search_engine=like`.

## 5. Тестове

Вендорнатият PHPUnit е 6.5.14 (несъвместим с PHP 8.1/8.4); тестовете са пускани с PHPUnit 9.6 phar извън repo:
```bash
curl -sSL -o /tmp/phpunit.phar https://phar.phpunit.de/phpunit-9.6.phar
# bootstrap: PSR-4 за Mantis\tests\ → tests/, vendor/autoload.php, tests/bootstrap.php
MANTIS_TESTSUITE_CATEGORY='обща категория' php8.1 /tmp/phpunit.phar --no-configuration --bootstrap <bootstrap> tests/rest/RestIssueSearchTest.php
```
`MANTIS_TESTSUITE_CATEGORY` е нов (RestBase::getCategory) - на dev проект 1 няма категория „General“.

- tests/rest/RestIssueSearchTest.php (нов): 16 теста, 201 проверки - OK и без демон, и с тунела към продукционния Sphinx.
- tests/rest/RestIssueFulltextCandidatesTest.php (нов): 3 теста, 23 проверки - OK. Регресионен за „филтри + таван“ със
  заместен доставчик (в процеса на теста): тикет, изместен от по-добри съвпадения извън филтъра, се намира; при таван
  сред кандидатите auto → like, fulltext → „incomplete“; непознат етикет → празни кандидати.
  Тестовете, които търсят по номер на нов тикет, подават `search_engine=like` (Sphinx не знае тикетите от теста).
  Тестовите файлове да не се пускат паралелно - един изтрива тикет, който друг зарежда (случаен 404).
- tests/rest/RestIssueQueryParamsTest.php: 18 теста, 116 проверки - OK.
- tests/rest/RestIssueProjectionTest.php: 12 теста - OK.
- RestFiltersTest / RestIssueTest: грешки от средата, несвързани с промените (тестът вика core функции в процеса си
  без логнат потребител → ERROR 18; етикетите на статусите са на български; липсват версии).

## 6. Променени файлове
- core/classes/BugFilterQuery.class.php - опции `sort_unrestricted`, `id_in`, `custom_field_criteria`,
  `date_ranges`, `status_changed` (винаги AND с филтъра, не отслабват видимостта).
- core/filter_api.php - filter_ensure_valid_filter търпи масив за sort/sort_direction, `asc` с малки букви, trim.
- api/rest/restcore/issues_rest.php - новите параметри, общ изпълнител `rest_issue_fetch_rows`, доставчик за
  пълнотекстово търсене.
- plugins/BarsySupport/classes/SearchService.php - `rest_issue_search`, `visible_issue_ids`, проверки за частни
  тикети/бележки, notes_count.
- plugins/BarsySupport/BarsySupport.php - `config()` (`rest_issues_fulltext`, `sphinx_indexes_private_notes`), `init()`.
- tests/rest/RestIssueSearchTest.php (нов), tests/rest/RestBase.php (категория от env/глобална).

## 7. Неизпълнено / за решение
- Подреждане по релевантност (`sort=relevance`) при Sphinx - не е направено.
- Маршрутът на плъгина не е превърнат в пълен заместител на /issues (по-добре да се ползва /issues + Sphinx).
- Ако потребителят няма `tag_view_threshold` в никой проект, ядрото пак игнорира етикетния филтър (не е променено).
- Отговорът при `offset` не връща `offset`/`limit` обратно; `page_count` е спрямо `limit`.
- Маршрутът на плъгина пита Sphinx за най-много 1000 съвпадения и филтрира след това (`truncated: true` → може да
  губи тикети при допълнителни филтри); кандидатите по id са направени само за /issues.
- Много чести думи (напр. „клиент“, 65 хил. документа) остават над тавана: auto → LIKE (~10-18 s на dev).
- Бавното LIKE търсене (~11 s за дума на dev) не е пипано - при `auto` се ползва само при недостъпен Sphinx.
