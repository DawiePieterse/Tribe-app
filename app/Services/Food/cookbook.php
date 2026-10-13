<?php

/*
 * Tribe's own cookbook: weeknight meals children like, for four (two grown-ups, two children), with
 * ingredients any Checkers, Pick n Pay or Woolworths has. Used to suggest meals when Claude isn't set
 * up. Each ingredient line names the ingredient it is (see Services\Food\Ingredients), 'staple' for
 * what every kitchen has, or null.
 *
 * @return list<array{title: string, minutes: int, kids: string, ingredients: list<array{0: string, 1: string|null}>, method: list<string>}>
 */

return [
    // Quick: 20 minutes or less.
    [
        'title' => 'Hoender-wraps met kaas en mielies',
        'minutes' => 15,
        'kids' => 'Elkeen rol sy eie; niks skerp nie.',
        'ingredients' => [['4 wraps', 'wraps'], ['2 koppies gaar hoender (oorskiet of ’n rotisserie-hoender)', 'hoender'], ['1 koppie gerasperde kaas', 'kaas'], ['1 blikkie mielies (410 g), gedreineer', 'mielies'], ['3 e mayonnaise', 'mayonnaise'], ['Blaarslaai of komkommer', null]],
        'method' => ['Trek die hoender in repies en meng met die mayonnaise.', 'Verhit die wraps 20 sekondes in die mikrogolf of droë pan.', 'Skep hoender, mielies, kaas en slaai op elke wrap en rol toe.', 'Rooster dit 1 minuut per kant in die pan sodat die kaas smelt.'],
    ],
    [
        'title' => 'Tuna-pasta met mielies',
        'minutes' => 20,
        'kids' => 'Romerig en sag, en die mielies maak dit soet.',
        'ingredients' => [['300 g pasta (skulpies of penne)', 'pasta'], ['2 blikkies tuna (170 g), gedreineer', 'tuna'], ['1 blikkie mielies, gedreineer', 'mielies'], ['½ koppie mayonnaise of room', 'room'], ['1 koppie gerasperde kaas', 'kaas'], ['Sout en peper', 'staple']],
        'method' => ['Kook die pasta in soutwater gaar en dreineer.', 'Roer die tuna, mielies, mayonnaise en die helfte van die kaas deur die warm pasta.', 'Geur en strooi die res van die kaas bo-oor.'],
    ],
    [
        'title' => 'Roereiers met kaas op roosterbrood',
        'minutes' => 10,
        'kids' => 'Ontbyt vir aandete: altyd ’n wenner.',
        'ingredients' => [['8 eiers', 'eiers'], ['¼ koppie melk', 'melk'], ['½ koppie gerasperde kaas', 'kaas'], ['8 snye brood', 'brood'], ['Botter', 'staple'], ['Tamaties om by te sit', 'tamaties']],
        'method' => ['Klits die eiers, melk en ’n knippie sout.', 'Smelt botter in ’n pan oor lae hitte en roer die eiers stadig tot net gaar.', 'Roer die kaas in en skep op die roosterbrood.'],
    ],
    [
        'title' => 'Wrap-pizzas',
        'minutes' => 15,
        'kids' => 'Kinders kies self hulle toppings.',
        'ingredients' => [['4 wraps of pitas', 'wraps'], ['½ koppie tamatiesous of pizzasous', 'tamatiesous'], ['1½ koppies gerasperde mozzarella of cheddar', 'kaas'], ['150 g ham, in blokkies', 'ham'], ['Mielies, pynappel of sampioene (opsioneel)', null]],
        'method' => ['Verhit die oond tot 200 °C.', 'Smeer sous op elke wrap en pak die ham, kaas en ander toppings op.', 'Bak 8–10 minute tot die kaas borrel en die rande bros is.'],
    ],
    [
        'title' => 'Kaas-en-ham quesadillas',
        'minutes' => 15,
        'kids' => 'Bros buitekant, gesmelte kaas binne, in driehoekies gesny.',
        'ingredients' => [['8 wraps', 'wraps'], ['2 koppies gerasperde kaas', 'kaas'], ['150 g ham', 'ham'], ['1 blikkie mielies of gebakte bone', 'mielies'], ['Suurroom of tamatiesous om in te doop', null]],
        'method' => ['Strooi kaas, ham en mielies op 4 wraps en sit die ander 4 bo-op.', 'Rooster een vir een in ’n droë pan, 2 minute per kant.', 'Sny in driehoekies en bedien met die doopsous.'],
    ],
    [
        'title' => 'Boerewors-rolletjies met tamatie-en-uie-sous',
        'minutes' => 20,
        'kids' => 'Soos by die braai, maar binne in die pan.',
        'ingredients' => [['500 g boerewors', 'wors'], ['6 worsrolletjies', 'brood'], ['1 blikkie tamatie-en-uie-smoor', 'tamaties'], ['Tamatiesous en mosterd', 'tamatiesous']],
        'method' => ['Braai die wors 12–15 minute in ’n pan oor medium hitte, en draai gereeld.', 'Verhit die tamatie-en-uie-smoor.', 'Sny die wors in rolletjie-lengtes, sit in die rolletjies en skep die smoor oor.'],
    ],
    [
        'title' => 'Hoender-noedels met groente',
        'minutes' => 15,
        'kids' => 'Slurp-noedels met min groente wat skaars opval.',
        'ingredients' => [['3 pakkies 2-minuut-noedels (hoendergeur)', 'noedels'], ['2 hoenderborsies, in dun repies', 'hoender'], ['2 koppies bevrore roerbraai-groente of ertjies', 'ertjies'], ['2 e soja- of sweet-chilli-sous', 'sojasous'], ['Olie', 'staple']],
        'method' => ['Roerbraai die hoender in ’n bietjie olie tot gaar, 5–6 minute.', 'Kook die noedels volgens die pakkie, met die groente saam in die water.', 'Dreineer, meng met die hoender, die geurpakkies en die sous.'],
    ],
    [
        'title' => 'Omelet-skyfies met ham en ertjies',
        'minutes' => 15,
        'kids' => 'In skywe gesny soos pizza.',
        'ingredients' => [['8 eiers', 'eiers'], ['100 g ham, in blokkies', 'ham'], ['1 koppie bevrore ertjies', 'ertjies'], ['½ koppie gerasperde kaas', 'kaas'], ['Brood om by te eet', 'brood'], ['Botter', 'staple']],
        'method' => ['Klits die eiers met ’n knippie sout.', 'Smelt botter in ’n groot pan, gooi die eiers in en strooi ham, ertjies en kaas oor.', 'Kook met ’n deksel oor lae hitte tot gestol, 6–8 minute.', 'Sny in skywe soos ’n pizza.'],
    ],
    [
        'title' => 'Kaas-macaroni (vinnige mac & cheese)',
        'minutes' => 20,
        'kids' => 'Die kos wat elke kind eet.',
        'ingredients' => [['300 g macaroni', 'pasta'], ['2 koppies melk', 'melk'], ['2 e botter', 'staple'], ['2 e koekmeel', 'staple'], ['2 koppies gerasperde cheddar', 'kaas'], ['Ertjies of broccoli om by te sit', 'broccoli']],
        'method' => ['Kook die macaroni gaar en dreineer.', 'Smelt die botter, roer die meel in, en klits die melk stadig by tot dik.', 'Roer die kaas in tot gesmelt en meng met die macaroni.'],
    ],

    // Medium: 20 to 40 minutes.
    [
        'title' => 'Spaghetti bolognaise',
        'minutes' => 35,
        'kids' => 'Wortel word fyn gerasper, so niemand sien dit nie.',
        'ingredients' => [['500 g maalvleis', 'maalvleis'], ['400 g spaghetti', 'pasta'], ['1 ui, fyngekap', 'uie'], ['1 wortel, fyn gerasper', 'wortels'], ['1 blikkie gekapte tamaties (410 g)', 'tamaties'], ['2 e tamatiepuree', 'tamatiepuree'], ['Gerasperde kaas om oor te strooi', 'kaas'], ['Olie, sout en peper', 'staple']],
        'method' => ['Braai die ui en wortel 5 minute in ’n bietjie olie.', 'Voeg die maalvleis by en braai tot bruin en krummelrig.', 'Roer tamaties en tamatiepuree in en prut 15 minute.', 'Kook intussen die spaghetti; bedien met die sous en kaas.'],
    ],
    [
        'title' => 'Krummelhoender-strepies met patat-skyfies uit die oond',
        'minutes' => 35,
        'kids' => 'Tuisgemaakte "nuggets" met soet skyfies.',
        'ingredients' => [['4 hoenderborsies, in repies', 'hoender'], ['2 eiers, geklits', 'eiers'], ['1½ koppies broodkrummels of fyn cornflakes', 'broodkrummels'], ['3 patats, in skyfies', 'patat'], ['Olie en sout', 'staple'], ['Tamatiesous of mayo om in te doop', 'tamatiesous']],
        'method' => ['Verhit die oond tot 220 °C. Gooi die patat-skyfies met olie en sout op ’n bakplaat en bak 10 minute voor.', 'Doop die hoender in eier en rol in die krummels.', 'Pak die hoender langs die skyfies en bak saam 18–20 minute, een keer omgedraai.'],
    ],
    [
        'title' => 'Maalvleis-tacos',
        'minutes' => 25,
        'kids' => 'Bou-jou-eie aan tafel.',
        'ingredients' => [['500 g maalvleis', 'maalvleis'], ['1 pakkie taco-speserye (of 2 t paprika en 1 t komyn)', null], ['8 wraps of taco-doppe', 'wraps'], ['1 koppie gerasperde kaas', 'kaas'], ['2 tamaties, in blokkies', 'tamaties'], ['Blaarslaai en suurroom', null]],
        'method' => ['Braai die maalvleis tot bruin, roer die speserye en ’n skeut water in en prut 5 minute.', 'Verhit die wraps.', 'Sit alles in bakkies op die tafel sodat elkeen self opskep.'],
    ],
    [
        'title' => 'Hoender à la king met rys',
        'minutes' => 30,
        'kids' => 'Sag, romerig en sonder enige skerp smaak.',
        'ingredients' => [['4 hoenderborsies, in blokkies', 'hoender'], ['1½ koppies rys', 'rys'], ['250 g sampioene, in skyfies', 'sampioene'], ['1 koppie bevrore ertjies', 'ertjies'], ['1 blikkie room-van-hoendersop of 250 ml room', 'room'], ['Olie, sout en peper', 'staple']],
        'method' => ['Kook die rys.', 'Braai die hoender in olie tot gaar, voeg die sampioene by en braai nog 3 minute.', 'Roer die sop of room en die ertjies in en prut 5 minute.', 'Bedien oor die rys.'],
    ],
    [
        'title' => 'Vis en skyfies uit die oond',
        'minutes' => 35,
        'kids' => 'Soos uit die viswinkel, sonder die diepbraai.',
        'ingredients' => [['4 stokvis- (hake-) filette of 12 visvingers', 'vis'], ['4 aartappels, in skyfies', 'aartappels'], ['1 koppie broodkrummels', 'broodkrummels'], ['1 eier, geklits', 'eiers'], ['Suurlemoen en tartaarsous', null], ['Olie en sout', 'staple']],
        'method' => ['Verhit die oond tot 220 °C en bak die skyfies met olie en sout 15 minute voor.', 'Doop die vis in eier en krummels (of gebruik bevrore visvingers).', 'Bak die vis saam met die skyfies 12–15 minute.'],
    ],
    [
        'title' => 'Hoender-roerbraai met rys',
        'minutes' => 25,
        'kids' => 'Effens soet heuning-sojasous.',
        'ingredients' => [['3 hoenderborsies, in repies', 'hoender'], ['1½ koppies rys', 'rys'], ['400 g roerbraai-groente (vars of bevrore)', 'broccoli'], ['2 e sojasous', 'sojasous'], ['2 e heuning', 'heuning'], ['Olie', 'staple']],
        'method' => ['Kook die rys.', 'Roerbraai die hoender in olie oor hoë hitte 5 minute.', 'Voeg die groente by en roerbraai nog 4 minute.', 'Roer die soja en heuning in en bedien op die rys.'],
    ],
    [
        'title' => 'Pasta-bak met ham en kaas',
        'minutes' => 40,
        'kids' => 'Bros kaas bo-op, sag binne.',
        'ingredients' => [['400 g penne', 'pasta'], ['200 g ham, in blokkies', 'ham'], ['2 koppies melk', 'melk'], ['2 e botter en 2 e meel', 'staple'], ['2 koppies gerasperde kaas', 'kaas'], ['1 koppie bevrore ertjies', 'ertjies']],
        'method' => ['Verhit die oond tot 200 °C en kook die pasta amper gaar.', 'Maak ’n witsous: smelt die botter, roer die meel in en klits die melk by tot dik. Roer die helfte van die kaas in.', 'Meng pasta, sous, ham en ertjies in ’n oondbak en strooi die res van die kaas oor.', 'Bak 15–20 minute tot goudbruin.'],
    ],
    [
        'title' => 'Frikkadelle met kapokaartappels',
        'minutes' => 40,
        'kids' => 'Klein, sagte frikkadelle wat in die hand pas.',
        'ingredients' => [['500 g maalvleis', 'maalvleis'], ['1 eier', 'eiers'], ['1 sny brood, in melk geweek', 'brood'], ['1 ui, fyn gerasper', 'uie'], ['6 aartappels', 'aartappels'], ['½ koppie melk en 2 e botter vir die kapok', 'melk'], ['Olie, sout en peper', 'staple']],
        'method' => ['Kook die aartappels gaar en druk fyn met die melk, botter en sout.', 'Meng die maalvleis, eier, brood, ui, sout en peper en rol in klein balletjies.', 'Braai die frikkadelle in ’n bietjie olie oor medium hitte, 10–12 minute, en draai gereeld.'],
    ],
    [
        'title' => 'Worsies, ertjies en kapokaartappel',
        'minutes' => 30,
        'kids' => '"Bangers and mash": eenvoudig en lekker.',
        'ingredients' => [['8 pork- of beesworsies', 'wors'], ['6 aartappels', 'aartappels'], ['2 koppies bevrore ertjies', 'ertjies'], ['½ koppie melk en 2 e botter', 'melk'], ['1 pakkie sous-poeier (bruinsous)', 'souspoeier']],
        'method' => ['Kook die aartappels gaar en druk fyn met melk, botter en sout.', 'Braai of rooster die worsies 15 minute tot gaar.', 'Kook die ertjies en maak die sous volgens die pakkie.'],
    ],

    // Longer: more than 40 minutes, a bit more work.
    [
        'title' => 'Cottage pie',
        'minutes' => 60,
        'kids' => 'Maalvleis onder ’n kombers van kapokaartappel en kaas.',
        'ingredients' => [['500 g maalvleis', 'maalvleis'], ['1 ui en 1 wortel, fyngekap', 'uie'], ['1 blikkie gekapte tamaties', 'tamaties'], ['1 koppie bevrore ertjies', 'ertjies'], ['6 aartappels', 'aartappels'], ['½ koppie melk en 2 e botter', 'melk'], ['1 koppie gerasperde kaas', 'kaas'], ['Olie, sout en peper', 'staple']],
        'method' => ['Kook die aartappels en druk fyn met melk, botter en sout.', 'Braai die ui en wortel, voeg die maalvleis by en braai tot bruin. Roer tamaties en ertjies in en prut 15 minute.', 'Skep die maalvleis in ’n oondbak, versprei die kapokaartappel bo-oor en strooi kaas oor.', 'Bak 25 minute by 200 °C tot goudbruin.'],
    ],
    [
        'title' => 'Lasagne',
        'minutes' => 75,
        'kids' => 'Laag op laag: maak ’n groot een en vries die helfte.',
        'ingredients' => [['500 g maalvleis', 'maalvleis'], ['1 pakkie lasagne-plate (250 g)', 'pasta'], ['1 ui, fyngekap', 'uie'], ['2 blikkies gekapte tamaties', 'tamaties'], ['3 koppies melk', 'melk'], ['3 e botter en 3 e meel', 'staple'], ['2 koppies gerasperde kaas', 'kaas']],
        'method' => ['Maak ’n maalvleissous: braai die ui en maalvleis, voeg die tamaties by en prut 20 minute.', 'Maak ’n witsous met die botter, meel en melk, en roer die helfte van die kaas in.', 'Pak in lae in ’n oondbak: sous, plate, witsous; eindig met witsous en kaas.', 'Bak 35–40 minute by 180 °C. Laat 10 minute staan voor jy sny.'],
    ],
    [
        'title' => 'Sagte bobotie met geelrys',
        'minutes' => 60,
        'kids' => 'Effens soet met appelkooskonfyt en sonder brand.',
        'ingredients' => [['500 g maalvleis', 'maalvleis'], ['1 ui, fyngekap', 'uie'], ['1 sny brood, in melk geweek', 'brood'], ['1 e matige kerriepoeier en 1 t borrie', 'kerriepoeier'], ['2 e appelkooskonfyt en 2 e blatjang', null], ['2 eiers en 1 koppie melk vir die bolaag', 'eiers'], ['1½ koppies rys (met borrie en rosyne gekook)', 'rys'], ['Olie en sout', 'staple']],
        'method' => ['Braai die ui, voeg kerrie en borrie by en braai 1 minuut.', 'Voeg die maalvleis by en braai tot bruin; meng die brood, konfyt en blatjang in.', 'Skep in ’n oondbak, klits die eiers en melk en gooi bo-oor.', 'Bak 35 minute by 180 °C. Kook intussen die geelrys.'],
    ],
    [
        'title' => 'Hoenderpastei met bladerdeeg',
        'minutes' => 60,
        'kids' => 'Die bros deeg-dak is die beste deel.',
        'ingredients' => [['1 gaar hoender (rotisserie), in stukke getrek', 'hoender'], ['1 rol bladerdeeg (400 g), ontdooi', 'bladerdeeg'], ['250 g sampioene', 'sampioene'], ['1 koppie ertjies en wortels', 'ertjies'], ['1 blikkie room-van-hoendersop', 'room'], ['1 eier om te verglans', 'eiers']],
        'method' => ['Verhit die oond tot 200 °C.', 'Braai die sampioene, meng met die hoender, groente en sop, en skep in ’n pasteibak.', 'Sit die bladerdeeg bo-oor, druk die rande vas en verglans met eier.', 'Bak 30–35 minute tot opgepof en goudbruin.'],
    ],
    [
        'title' => 'Hoenderboudjies en aartappels in een pan',
        'minutes' => 55,
        'kids' => 'Boudjies om met die hand te eet.',
        'ingredients' => [['8 hoenderboudjies of -dye', 'hoender'], ['6 aartappels, in kwarte', 'aartappels'], ['3 wortels, in stukke', 'wortels'], ['2 e heuning en 2 e sojasous of BBQ-sous', 'heuning'], ['Olie, sout en paprika', 'staple']],
        'method' => ['Verhit die oond tot 200 °C.', 'Gooi die aartappels en wortels met olie en sout in ’n groot oondpan.', 'Smeer die hoender met heuning, soja en paprika en pak tussen die groente.', 'Bak 45 minute, een keer omgedraai, tot die hoender goudbruin en gaar is.'],
    ],
    [
        'title' => 'Matige botterhoender met rys',
        'minutes' => 50,
        'kids' => 'Romerige "butter chicken" sonder die brand.',
        'ingredients' => [['4 hoenderborsies of 6 dye, in blokkies', 'hoender'], ['1 ui, fyngekap', 'uie'], ['2 e matige botterhoender-pasta of kerriepoeier', 'kerriepoeier'], ['1 blikkie tamatiepuree of gekapte tamaties', 'tamaties'], ['250 ml room', 'room'], ['1½ koppies basmati-rys', 'rys'], ['Botter en olie', 'staple']],
        'method' => ['Braai die ui in botter en olie, voeg die pasta by en braai 1 minuut.', 'Voeg die hoender by en braai tot verseël.', 'Roer die tamatie en room in en prut 20 minute.', 'Kook intussen die rys en bedien saam.'],
    ],
    [
        'title' => 'Botterskorsiesop met kaasbroodjies',
        'minutes' => 45,
        'kids' => 'Soet, oranje sop met kaasbrood om in te doop.',
        'ingredients' => [['1 botterskorsie (of 800 g gesny)', 'botterskorsie'], ['1 ui', 'uie'], ['2 aartappels', 'aartappels'], ['1 liter hoender- of groente-aftreksel', null], ['½ koppie room', 'room'], ['4 broodrolletjies en 1 koppie kaas', 'brood'], ['Botter', 'staple']],
        'method' => ['Braai die ui in botter, voeg die skorsie, aartappels en aftreksel by en prut 25 minute tot sag.', 'Maal glad met ’n staafmenger en roer die room in.', 'Strooi kaas op die rolletjies en rooster 5 minute onder die oondrooster.'],
    ],
];
