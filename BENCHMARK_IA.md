# Benchmark des Modèles IA (RAG) - Serveur ARM

| Modèle | Tokens | Vitesse Lecture | Génération | Temps Total | Qualité |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Luth (1.7B)** | 3059 | 13.50 ms/tok | 10.43 tok/s | **52s** | **4.5/5** |
| **Gemma 4 (2.6B)** | 2802 | 38.98 ms/tok | 12.83 tok/s | **1m 51s** | **5/5** |
| **Qwen 2.5 (1.5B)** | 2932 | 11.57 ms/tok | 25.00 tok/s | **34s** | 2/5 |
| **Croissant (1.3B)** | - | - | - | **Crash** | 0/5 |

## Journal des Tests

### Question : "Qu'est-ce qui a fait rater l'attentat contre Hitler ?"

| Modèle | Résultat | Analyse |
| :--- | :--- | :--- |
| **Gemma 4** | "L'attentat... a été raté car... Hitler... avait l'habitude de changer ses programmes à l'improviste..." | **Correct**. Mentionne le départ prématuré de Hitler. |
| **Luth 1.7B** | "L'ensemble des éléments ont empêché l'explosion à temps." | **Imprécis/Erreur**. Prétend que la bombe n'a pas explosé. |

### Question : "Qui est Émile Henry ?"

| Modèle | Réponse intégrale |
| :--- | :--- |
| **Luth 1.7B (V2)** | "Emile Henry, né en Espagne en 1872, était un poète et militant anarchiste. Il a fait des interventions publiques durant les années 1890 à Paris, notamment dans la rue des Bons-Enfants et au Café Terminus, exprimant sa révolte contre l’ordre social putréfié. Son œuvre soulève de profondes questions sur le pouvoir, la liberté et la lutte entre autorité et individualisme. Il a été exécuté en 1894 pour ses actions politiques violentes." |
| **Gemma 4** | "Emile Henry est un des grands poètes du 19ème siècle. Il a dédier sa vie à la recherche obstinée non pas du vers juste, mais de la révolte – cet acte souverain par lequel l’individu qui tend à la liberté devient un individu libre, et par lequel le poète devient poète... [Réponse magnifique sur la révolte et la bourgeoisie]" |
