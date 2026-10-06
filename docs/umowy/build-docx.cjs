// Generates the Word templates in docs/umowy/: node docs/umowy/build-docx.cjs
// Text markup: **bold**, {{placeholder}} (highlighted for filling in).
const fs = require('fs')
const path = require('path')
const {
  AlignmentType, BorderStyle, Document, Footer, HeadingLevel, LevelFormat, Packer, PageNumber,
  Paragraph, Table, TableCell, TableRow, TextRun, WidthType,
} = require('docx')

const FONT = 'Calibri'

function runs(text, base = {}) {
  return text.split(/(\*\*[^*]+\*\*|\{\{[^}]+\}\})/).filter(Boolean).map((part) => {
    if (part.startsWith('**')) return new TextRun({ ...base, text: part.slice(2, -2), bold: true })
    if (part.startsWith('{{')) return new TextRun({ ...base, text: `[${part.slice(2, -2)}]`, highlight: 'yellow' })
    return new TextRun({ ...base, text: part })
  })
}

const p = (text, opts = {}) => new Paragraph({ children: runs(text), spacing: { after: 120 }, alignment: AlignmentType.JUSTIFIED, ...opts })

let instance = 0
/** A "§" section: centred heading and numbered paragraphs (numbering restarts in every section). */
function section(title, items) {
  instance++
  const out = [new Paragraph({ heading: HeadingLevel.HEADING_2, alignment: AlignmentType.CENTER, children: runs(title), spacing: { before: 280, after: 140 } })]
  for (const item of items) {
    if (Array.isArray(item)) {
      for (const sub of item) out.push(new Paragraph({ children: runs(sub), numbering: { reference: 'pkt', level: 0, instance: 1000 + instance * 50 + out.length }, spacing: { after: 80 }, alignment: AlignmentType.JUSTIFIED, indent: { left: 1080, hanging: 360 } }))
    } else {
      out.push(new Paragraph({ children: runs(item), numbering: { reference: 'ust', level: 0, instance }, spacing: { after: 100 }, alignment: AlignmentType.JUSTIFIED }))
    }
  }
  return out
}

/** Lettered sub-points restart per list, so give each list one instance. */
function letters(items) {
  instance++
  return items.map((text) => new Paragraph({ children: runs(text), numbering: { reference: 'pkt', level: 0, instance: 5000 + instance }, spacing: { after: 80 }, alignment: AlignmentType.JUSTIFIED, indent: { left: 1080, hanging: 360 } }))
}

function sectionWithLetters(title, parts) {
  instance++
  const own = instance
  const out = [new Paragraph({ heading: HeadingLevel.HEADING_2, alignment: AlignmentType.CENTER, children: runs(title), spacing: { before: 280, after: 140 } })]
  for (const part of parts) {
    if (typeof part === 'string') out.push(new Paragraph({ children: runs(part), numbering: { reference: 'ust', level: 0, instance: own }, spacing: { after: 100 }, alignment: AlignmentType.JUSTIFIED }))
    else out.push(...letters(part.letters))
  }
  return out
}

const noBorder = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' }
function signatures(left, right) {
  const cell = (label) => new TableCell({
    width: { size: 4513, type: WidthType.DXA },
    borders: { top: noBorder, bottom: noBorder, left: noBorder, right: noBorder },
    children: [
      new Paragraph({ spacing: { before: 900 }, alignment: AlignmentType.CENTER, children: [new TextRun('……………………………………………')] }),
      new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text: label, size: 18 })] }),
    ],
  })
  return new Table({ width: { size: 9026, type: WidthType.DXA }, columnWidths: [4513, 4513], rows: [new TableRow({ children: [cell(left), cell(right)] })] })
}

function note(text) {
  return new Paragraph({
    children: runs(text, { size: 18, italics: true, color: '555555' }),
    border: { top: { style: BorderStyle.SINGLE, size: 4, color: 'BBBBBB', space: 4 }, bottom: { style: BorderStyle.SINGLE, size: 4, color: 'BBBBBB', space: 4 } },
    spacing: { after: 240 },
  })
}

function doc(title, subtitle, body) {
  return new Document({
    creator: 'GastroFlowx',
    title,
    styles: {
      default: { document: { run: { font: FONT, size: 21 } } },
      paragraphStyles: [
        { id: 'Heading1', name: 'Heading 1', basedOn: 'Normal', next: 'Normal', run: { size: 30, bold: true, color: '312E81' }, paragraph: { alignment: AlignmentType.CENTER, spacing: { after: 80 } } },
        { id: 'Heading2', name: 'Heading 2', basedOn: 'Normal', next: 'Normal', run: { size: 22, bold: true }, paragraph: { keepNext: true } },
      ],
    },
    numbering: {
      config: [
        { reference: 'ust', levels: [{ level: 0, format: LevelFormat.DECIMAL, text: '%1.', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 540, hanging: 360 } } } }] },
        { reference: 'pkt', levels: [{ level: 0, format: LevelFormat.LOWER_LETTER, text: '%1)', alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 1080, hanging: 360 } } } }] },
      ],
    },
    sections: [{
      properties: { page: { margin: { top: 1300, bottom: 1300, left: 1440, right: 1440 } } },
      footers: {
        default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text: `${title} – strona `, size: 16, color: '888888' }), new TextRun({ children: [PageNumber.CURRENT], size: 16, color: '888888' }), new TextRun({ text: ' z ', size: 16, color: '888888' }), new TextRun({ children: [PageNumber.TOTAL_PAGES], size: 16, color: '888888' })] })] }),
      },
      children: [
        new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun(title)] }),
        new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 240 }, children: [new TextRun({ text: subtitle, color: '555555' })] }),
        ...body,
      ],
    }],
  })
}

const PROVIDER = '**Karolem Macieja**, prowadzącym działalność gospodarczą pod firmą KM ENTERPRISES Karol Macieja, ul. Dmowskiego 101/88, 60-204 Poznań, NIP: {{NIP}}, e-mail: kontakt@gastroflowx.pl'
const RESTAURANT = '{{pełna nazwa firmy restauracji}} z siedzibą w {{adres}}, NIP: {{NIP}}, reprezentowaną przez {{imię i nazwisko, funkcja}}'

// ---------------------------------------------------------------- 1. DPA
const dpa = doc('Umowa powierzenia przetwarzania danych osobowych', 'dotycząca korzystania z systemu GastroFlowx (art. 28 RODO)', [
  note('WZÓR. Pola zaznaczone na żółto uzupełnij przed podpisaniem i usuń tę ramkę. Wzór nie zastępuje porady prawnej – przy nietypowych ustaleniach skonsultuj treść z prawnikiem.'),
  p('zawarta w dniu {{data}} w {{miejscowość}} pomiędzy:'),
  p(RESTAURANT + ', zwaną dalej **„Administratorem”**,'),
  p('a'),
  p(PROVIDER + ', zwanym dalej **„Podmiotem przetwarzającym”**,'),
  p('łącznie zwanymi **„Stronami”**.'),

  ...section('§ 1. Przedmiot umowy', [
    'Administrator powierza Podmiotowi przetwarzającemu przetwarzanie danych osobowych w trybie art. 28 Rozporządzenia Parlamentu Europejskiego i Rady (UE) 2016/679 (**„RODO”**) w zakresie i celu określonym w niniejszej umowie.',
    'Powierzenie następuje w związku z umową o korzystanie z systemu CRM GastroFlowx z dnia {{data umowy głównej lub „zaakceptowania oferty”}} (**„Umowa główna”**), obejmującą rozszerzenie Chrome działające w Gmailu, serwer aplikacji i panel administracyjny (**„System”**).',
    'Podmiot przetwarzający przetwarza powierzone dane wyłącznie na udokumentowane polecenie Administratora. Za polecenie uznaje się Umowę główną, niniejszą umowę oraz działania użytkowników Administratora w Systemie (dodawanie, zmiana, udostępnianie i usuwanie danych). Jeżeli prawo wymaga innego przetwarzania, Podmiot przetwarzający poinformuje o tym Administratora przed jego rozpoczęciem, chyba że prawo tego zakazuje.',
  ]),

  ...sectionWithLetters('§ 2. Charakter, cel, rodzaj danych i kategorie osób', [
    'Charakter przetwarzania: przechowywanie, porządkowanie, wyświetlanie, udostępnianie użytkownikom Administratora, modyfikowanie i usuwanie danych w Systemie, w tym tworzenie kopii zapasowych w ramach usługi hostingowej.',
    'Cel przetwarzania: świadczenie usługi CRM – prowadzenie bazy kontaktów i klientów restauracji, notatek, rezerwacji, zadań, przypomnień oraz historii korespondencji e-mail z klientami.',
    'Kategorie osób, których dane dotyczą:',
    { letters: ['klienci i kontrahenci Administratora (goście, firmy, dostawcy) oraz ich osoby kontaktowe,', 'pracownicy i współpracownicy Administratora korzystający z Systemu (użytkownicy).'] },
    'Rodzaj danych:',
    { letters: [
      'klienci i kontrahenci: imię i nazwisko, adres e-mail, telefon, firma, kategoria i status, pola dodatkowe wprowadzone przez użytkowników (np. alergie, preferencje, NIP), notatki, rezerwacje, zadania i przypomnienia,',
      'metadane korespondencji e-mail z klientami zapisanymi w Systemie: temat, nadawca, data, kierunek, identyfikatory wiadomości i wątku oraz fragment treści do 2000 znaków (bez pełnej treści i załączników),',
      'użytkownicy: imię i nazwisko, służbowy adres e-mail, rola, przydział licencji, preferencje, informacje o autorstwie wpisów, opiekunie kontaktu i udostępnieniach oraz dane techniczne logowania.',
    ] },
    'Administrator nie będzie wprowadzał do Systemu szczególnych kategorii danych (art. 9 RODO) w zakresie szerszym niż niezbędny do obsługi klienta (np. informacja o alergii pokarmowej przekazana przez gościa) i odpowiada za istnienie podstawy prawnej ich przetwarzania.',
  ]),

  ...section('§ 3. Czas trwania', [
    'Umowa obowiązuje przez czas obowiązywania Umowy głównej, a po jej zakończeniu – do czasu usunięcia lub zwrotu danych zgodnie z § 8.',
  ]),

  ...sectionWithLetters('§ 4. Obowiązki Podmiotu przetwarzającego', [
    'Podmiot przetwarzający zobowiązuje się:',
    { letters: [
      'przetwarzać dane wyłącznie w celu i zakresie określonym w umowie oraz na terytorium Europejskiego Obszaru Gospodarczego (serwery w Polsce), z zastrzeżeniem § 5,',
      'dopuszczać do danych wyłącznie osoby upoważnione, zobowiązane do zachowania ich w tajemnicy, także po ustaniu współpracy,',
      'stosować środki techniczne i organizacyjne zapewniające bezpieczeństwo danych, o których mowa w art. 32 RODO, opisane w Załączniku nr 1,',
      'pomagać Administratorowi, w miarę możliwości, w realizacji żądań osób, których dane dotyczą (dostęp, sprostowanie, usunięcie, ograniczenie, przeniesienie, sprzeciw) – w szczególności przekazać na żądanie eksport danych wskazanej osoby,',
      'pomagać Administratorowi w wywiązywaniu się z obowiązków z art. 32–36 RODO, uwzględniając charakter przetwarzania i dostępne informacje,',
      'prowadzić rejestr kategorii czynności przetwarzania, o którym mowa w art. 30 ust. 2 RODO, jeżeli jest do tego zobowiązany,',
      'niezwłocznie informować Administratora, jeżeli jego zdaniem wydane polecenie narusza RODO lub inne przepisy o ochronie danych.',
    ] },
  ]),

  ...sectionWithLetters('§ 5. Dalsze powierzenie (podprocesorzy)', [
    'Administrator wyraża ogólną zgodę na dalsze powierzenie przetwarzania. Na dzień zawarcia umowy z usług podprocesorów korzysta się w następującym zakresie:',
    { letters: [
      '**SEOHOST.pl** – hosting serwera aplikacji i bazy danych, serwery w Polsce,',
      '{{inny podprocesor – jeżeli dotyczy, w przeciwnym razie usuń}}.',
    ] },
    'Dane przesyłane bezpośrednio między przeglądarką użytkownika a Google (Gmail, Kalendarz Google, opcjonalna integracja z kontem Google) oraz dane diagnostyczne biblioteki InboxSDK (Streak), które nie obejmują treści wiadomości ani danych CRM, nie są przetwarzane przez Podmiot przetwarzający w ramach niniejszej umowy – zasady opisuje polityka prywatności GastroFlowx.',
    'O zamiarze zmiany lub dodania podprocesora Podmiot przetwarzający poinformuje Administratora e-mailem co najmniej 14 dni wcześniej. Administrator może w tym terminie zgłosić uzasadniony sprzeciw; w razie braku porozumienia każda ze Stron może wypowiedzieć Umowę główną.',
    'Podmiot przetwarzający nakłada na podprocesora te same obowiązki ochrony danych, co wynikające z niniejszej umowy, i odpowiada wobec Administratora za ich wykonanie.',
  ]),

  ...section('§ 6. Naruszenia ochrony danych', [
    'Podmiot przetwarzający zgłasza Administratorowi stwierdzone naruszenie ochrony danych osobowych bez zbędnej zwłoki, nie później niż w ciągu **36 godzin** od jego stwierdzenia, na adres e-mail: {{adres e-mail Administratora do zgłoszeń}}.',
    'Zgłoszenie zawiera, w miarę dostępności, informacje wskazane w art. 33 ust. 3 RODO: charakter naruszenia, kategorie i przybliżoną liczbę osób i rekordów, prawdopodobne konsekwencje oraz podjęte lub proponowane środki. Informacje niedostępne w chwili zgłoszenia przekazuje się niezwłocznie później.',
    'Zgłoszenie naruszenia do Prezesa UODO i zawiadomienie osób, których dane dotyczą, należy do Administratora.',
  ]),

  ...section('§ 7. Kontrola', [
    'Podmiot przetwarzający udostępnia Administratorowi informacje niezbędne do wykazania spełnienia obowiązków z art. 28 RODO, w szczególności odpowiada na pytania Administratora w ciągu 14 dni.',
    'Administrator może przeprowadzić audyt lub inspekcję, samodzielnie lub przez upoważnionego audytora zobowiązanego do zachowania tajemnicy, po zawiadomieniu Podmiotu przetwarzającego z co najmniej 14-dniowym wyprzedzeniem, w dniach i godzinach pracy, nie częściej niż raz w roku – chyba że kontrola jest związana z naruszeniem ochrony danych lub żądaniem organu nadzorczego. Koszty audytu ponosi Administrator.',
  ]),

  ...section('§ 8. Zakończenie przetwarzania', [
    'Po zakończeniu Umowy głównej Podmiot przetwarzający, zgodnie z decyzją Administratora, zwraca dane (eksport w formacie CSV lub JSON) albo je usuwa, w terminie **30 dni**, chyba że prawo nakazuje ich dalsze przechowywanie.',
    'Wniosek o eksport Administrator składa przed upływem terminu z ust. 1. Kopie zapasowe są usuwane w cyklu ich rotacji u dostawcy hostingu.',
    'Na żądanie Administratora Podmiot przetwarzający potwierdza usunięcie danych e-mailem.',
  ]),

  ...section('§ 9. Odpowiedzialność', [
    'Podmiot przetwarzający odpowiada za szkody spowodowane przetwarzaniem niezgodnym z niniejszą umową lub RODO na zasadach art. 82 RODO.',
    'Administrator odpowiada za zgodność z prawem danych wprowadzanych do Systemu przez jego użytkowników, za istnienie podstaw prawnych ich przetwarzania oraz za nadanie upoważnień swoim pracownikom i współpracownikom.',
    '{{opcjonalnie: ograniczenie odpowiedzialności, np. do wysokości wynagrodzenia z Umowy głównej za ostatnie 12 miesięcy, z wyjątkiem szkody wyrządzonej umyślnie – usuń, jeżeli nie dotyczy}}',
  ]),

  ...section('§ 10. Postanowienia końcowe', [
    'Zmiany umowy wymagają formy pisemnej lub dokumentowej (e-mail) pod rygorem nieważności.',
    'W sprawach nieuregulowanych stosuje się RODO, ustawę o ochronie danych osobowych oraz Kodeks cywilny.',
    'Spory rozstrzyga sąd właściwy dla siedziby {{Administratora / Podmiotu przetwarzającego}}.',
    'Umowę sporządzono w dwóch jednobrzmiących egzemplarzach, po jednym dla każdej ze Stron, albo w formie elektronicznej opatrzonej podpisami elektronicznymi Stron.',
  ]),

  signatures('Administrator', 'Podmiot przetwarzający'),

  new Paragraph({ heading: HeadingLevel.HEADING_2, pageBreakBefore: true, alignment: AlignmentType.CENTER, children: [new TextRun('Załącznik nr 1 – Środki techniczne i organizacyjne')], spacing: { after: 160 } }),
  ...letters([
    'Szyfrowanie połączeń (HTTPS/TLS) między rozszerzeniem, panelem administracyjnym i serwerem.',
    'Hasła użytkowników i tokeny logowania przechowywane wyłącznie w postaci skrótów kryptograficznych; tokeny rozszerzenia wygasają po 30 dniach, sesja panelu administracyjnego po 2 godzinach bezczynności.',
    'Logiczna separacja danych restauracji – każdy użytkownik ma dostęp wyłącznie do danych swojej restauracji.',
    'Kontrola dostępu wewnątrz restauracji: karta kontaktu jest domyślnie widoczna tylko dla jej opiekuna; inni użytkownicy uzyskują dostęp wyłącznie przez udostępnienie w zakresie wybranym przez opiekuna; karty w kategoriach prywatnych widzi tylko ich właściciel.',
    'Indywidualne konta użytkowników, nadawane i odbierane przez administratora systemu; możliwość natychmiastowego wylogowania użytkownika ze wszystkich urządzeń.',
    'Ograniczenie zakresu danych z Gmaila do metadanych korespondencji z klientami zapisanymi w Systemie; token dostępu Google pozostaje w przeglądarce użytkownika.',
    'Ochrona przed atakami: limit prób logowania, ochrona CSRF panelu, walidacja danych wejściowych.',
    'Serwery w Polsce (UE); dzienniki serwera przechowywane do 14 dni; kopie zapasowe w ramach usługi hostingowej.',
    'Dostęp do serwera i bazy danych wyłącznie dla Podmiotu przetwarzającego, z użyciem uwierzytelnionego połączenia SSH.',
    'Aktualizacje bezpieczeństwa oprogramowania wdrażane niezwłocznie po ich udostępnieniu.',
  ]),
])

// ---------------------------------------------------------------- 2. Employee
const employee = doc('Porozumienie w sprawie korzystania z systemu GastroFlowx', 'oraz upoważnienie do przetwarzania danych osobowych klientów', [
  note('WZÓR dla restauracji (pracodawcy). Pola zaznaczone na żółto uzupełnij, a niepotrzebne punkty usuń. Porozumienie można stosować także wobec osób zatrudnionych na podstawie umów cywilnoprawnych – wtedy „Pracodawca” oznacza zleceniodawcę, a „Pracownik” – zleceniobiorcę. W razie wątpliwości skonsultuj treść z prawnikiem.'),
  p('zawarte w dniu {{data}} w {{miejscowość}} pomiędzy:'),
  p(RESTAURANT + ', zwaną dalej **„Pracodawcą”**,'),
  p('a'),
  p('{{imię i nazwisko}}, stanowisko: {{stanowisko}}, zwanym/zwaną dalej **„Pracownikiem”**.'),

  ...section('§ 1. Przedmiot', [
    'Porozumienie określa zasady korzystania przez Pracownika z systemu CRM **GastroFlowx** (rozszerzenie przeglądarki Chrome działające w Gmailu, **„System”**), w którym Pracodawca prowadzi bazę swoich klientów i kontrahentów, notatki, rezerwacje, zadania, przypomnienia oraz historię korespondencji z klientami.',
    'Dostawcą Systemu jest KM ENTERPRISES Karol Macieja, który przetwarza dane w imieniu Pracodawcy na podstawie umowy powierzenia przetwarzania danych.',
  ]),

  ...section('§ 2. Konto i dostęp', [
    'Pracownik otrzymuje osobiste konto w Systemie. Login i hasło są poufne – Pracownik nie udostępnia ich innym osobom, w tym współpracownikom, i nie loguje się na cudze konto.',
    'Pracownik korzysta z Systemu przy użyciu **służbowej skrzynki e-mail** {{adres służbowej skrzynki}} i nie łączy z Systemem prywatnych kont pocztowych ani prywatnego konta Google.',
    'Połączenie Systemu z kontem Google (historia korespondencji, Kalendarz Google) oraz prywatny link kalendarza są opcjonalne. Pracownik nie przekazuje linku kalendarza osobom trzecim.',
    'Pracownik niezwłocznie zgłasza Pracodawcy utratę urządzenia, podejrzenie przejęcia konta lub inne zdarzenie mogące naruszać bezpieczeństwo danych. Pracodawca zleca wtedy dostawcy Systemu zablokowanie konta i wylogowanie go ze wszystkich urządzeń.',
  ]),

  ...sectionWithLetters('§ 3. Upoważnienie do przetwarzania danych osobowych', [
    'Na podstawie art. 29 i art. 32 ust. 4 RODO Pracodawca upoważnia Pracownika do przetwarzania danych osobowych klientów i kontrahentów Pracodawcy w Systemie – w zakresie niezbędnym do wykonywania obowiązków na stanowisku {{stanowisko}}, obejmującym:',
    { letters: [
      'dodawanie, przeglądanie i aktualizowanie danych kontaktowych oraz kart klientów,',
      'prowadzenie notatek, rezerwacji, zadań i przypomnień,',
      'zapisywanie w Systemie metadanych korespondencji e-mail z klientami,',
      'udostępnianie kart klientów współpracownikom zgodnie z § 4.',
    ] },
    'Upoważnienie obowiązuje od dnia podpisania porozumienia do dnia zakończenia współpracy albo jego wcześniejszego odwołania.',
    'Pracownik zobowiązuje się zachować w tajemnicy dane osobowe i sposoby ich zabezpieczenia, także po zakończeniu współpracy, oraz nie kopiować danych z Systemu poza narzędzia wskazane przez Pracodawcę.',
  ]),

  ...sectionWithLetters('§ 4. Opiekun klienta, udostępnianie i korespondencja firmowa', [
    'W Systemie każdy klient ma **opiekuna** – osobę, która dodała go do Systemu lub przejęła opiekę. Karta klienta jest domyślnie widoczna tylko dla opiekuna.',
    'Pracownik udostępnia karty klientów współpracownikom zgodnie z zasadami ustalonymi przez Pracodawcę:',
    { letters: [
      '{{np. klientów weselnych i firmowych udostępnia się całemu zespołowi w zakresie danych kontaktowych i rezerwacji}},',
      '{{np. na prośbę o dostęp opiekun odpowiada w ciągu 1 dnia roboczego}},',
      '{{inne zasady Pracodawcy lub usuń}}.',
    ] },
    'W kategoriach oznaczonych jako **„Prywatne karty”** (korespondencja firmowa) każdy pracownik prowadzi własną kartę kontaktu. Do wspólnej puli zespołu Pracownik dodaje maile istotne dla pracy zespołu, w szczególności {{np. oferty, cenniki, ustalenia z dostawcami}}.',
    'Prywatność kart oznacza ograniczenie widoczności między współpracownikami w Systemie. Administratorem wszystkich danych w Systemie, w tym danych na prywatnych kartach, pozostaje Pracodawca, który – w uzasadnionych przypadkach, np. przy zakończeniu współpracy – może polecić przekazanie kart innej osobie lub eksport danych.',
    'Pracownik nie wprowadza do Systemu informacji niezwiązanych z obsługą klientów ani prywatnej korespondencji, a w polach i notatkach nie zapisuje danych szczególnych kategorii ponad to, co niezbędne do obsługi klienta (np. alergia pokarmowa zgłoszona przez gościa).',
  ]),

  ...sectionWithLetters('§ 5. Dane Pracownika w Systemie', [
    'W związku z korzystaniem z Systemu Pracodawca przetwarza następujące dane Pracownika: imię i nazwisko, służbowy adres e-mail, rolę, informację o przydziale licencji, preferencje (np. język, domyślne godziny przypomnień) oraz informacje o autorstwie wpisów, przypisanych zadaniach i udostępnieniach.',
    'Celem przetwarzania jest organizacja pracy i obsługi klientów (art. 6 ust. 1 lit. b i f RODO). Dane są przetwarzane przez czas zatrudnienia, a po jego zakończeniu konto jest usuwane lub dezaktywowane w ciągu {{np. 30}} dni.',
    'System nie służy do monitorowania Pracownika ani jego poczty elektronicznej w rozumieniu art. 22³ Kodeksu pracy:',
    { letters: [
      'nie zapisuje pełnej treści wiadomości ani załączników – wyłącznie metadane (temat, nadawca, data, krótki fragment) korespondencji z klientami zapisanymi w Systemie,',
      'nie rejestruje czasu pracy, aktywności przeglądarki ani położenia Pracownika.',
    ] },
    'Pracownikowi przysługuje prawo dostępu do swoich danych, ich sprostowania, usunięcia lub ograniczenia przetwarzania oraz wniesienia sprzeciwu i skargi do Prezesa UODO. Szczegóły zawiera klauzula informacyjna Pracodawcy {{oraz polityka prywatności GastroFlowx: app.gastroflowx.pl/polityka-prywatnosci.html}}.',
  ]),

  ...section('§ 6. Zakończenie współpracy', [
    'Przed zakończeniem współpracy Pracownik przekazuje opiekę nad swoimi klientami osobie wskazanej przez Pracodawcę (funkcja **„Przekaż opiekę”**) i dodaje do wspólnej puli maile z prywatnych kart potrzebne zespołowi.',
    'Dane klientów zgromadzone w Systemie należą do zasobów Pracodawcy i mogą stanowić tajemnicę jego przedsiębiorstwa. Pracownik nie zabiera ich, nie kopiuje i nie wykorzystuje po zakończeniu współpracy.',
    'Z dniem zakończenia współpracy Pracodawca zleca dostawcy Systemu usunięcie konta Pracownika lub zwolnienie przydzielonego mu miejsca w licencji.',
  ]),

  ...section('§ 7. Postanowienia końcowe', [
    'Naruszenie postanowień porozumienia może stanowić naruszenie obowiązków pracowniczych i skutkować odpowiedzialnością przewidzianą w przepisach prawa pracy oraz przepisach o ochronie danych osobowych.',
    'Pracownik oświadcza, że zapoznał się z instrukcją obsługi Systemu GastroFlowx w zakresie rozdziałów 4 i 5.',
    'Porozumienie sporządzono w dwóch egzemplarzach, po jednym dla każdej ze Stron.',
  ]),

  signatures('Pracodawca', 'Pracownik'),
])

const out = path.join(__dirname)
Promise.all([
  Packer.toBuffer(dpa).then((b) => fs.writeFileSync(path.join(out, 'GastroFlowx-umowa-powierzenia.docx'), b)),
  Packer.toBuffer(employee).then((b) => fs.writeFileSync(path.join(out, 'GastroFlowx-porozumienie-z-pracownikiem.docx'), b)),
]).then(() => console.log('✓ docs/umowy/*.docx'))
