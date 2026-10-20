# Scripts

Outillage du dépôt : traduction de la documentation.

## `translate.py`

Traduit la documentation de `docs/fr` vers les langues déclarées dans
`docs/language_names.yml` (`docs/en`, `docs/es`, `docs/pt`, `docs/de`, `docs/ko`).

```bash
# Traduire ce qui manque ou ce qui a changé
LLM_API_KEY=sk-... python scripts/translate.py --lang en

# Toutes les langues, tout retraduire
LLM_API_KEY=sk-... python scripts/translate.py --lang all --force

# Vérifier sans clé API (code retour 1 si une page manque)
python scripts/translate.py --lang all --check

# Voir le plan sans rien écrire
python scripts/translate.py --lang en --dry-run

# Amorcer une nouvelle langue sans API (copie du français)
python scripts/translate.py --lang es --provider copy
```

### Variables d'environnement

| Variable | Défaut | Rôle |
| --- | --- | --- |
| `LLM_API_KEY` (ou `OPENAI_API_KEY`) | — | Clé de l'API compatible OpenAI |
| `LLM_BASE_URL` | `https://api.deepseek.com/v1` | Endpoint (`.../chat/completions`) |
| `LLM_MODEL` | `deepseek-chat` | Modèle utilisé |

Fonctionne avec DeepSeek, OpenAI, Mistral, Groq, Ollama en local, etc. : tout
service exposant `POST /chat/completions`.

### Fonctionnement

1. lecture de `docs/fr/**/*.md` ;
2. comparaison avec `docs/<lang>/.manifest.json` (empreinte SHA-256 de la source) ;
3. pour chaque page manquante ou modifiée : appel du modèle avec
   `scripts/prompts/translate.md` ;
4. contrôles automatiques (blocs de code, titres, admonitions, longueur) avec
   jusqu'à 3 tentatives ;
5. écriture de `docs/<lang>/<page>.md` et mise à jour du manifeste.

Le prompt impose de **copier les blocs de code à l'identique** : ils
correspondent aux fichiers de `docs_src/`, eux-mêmes utilisés par les tests.

### Ajouter une langue

1. ajoutez le code dans `docs/language_names.yml` (et dans `mkdocs.yml`) ;
2. ajoutez éventuellement sa colonne dans `scripts/prompts/glossary.md` ;
3. lancez `python scripts/translate.py --lang <code>`.

## Prompts

- `prompts/translate.md` : prompt de traduction (règles, structure, style) ;
- `prompts/glossary.md` : terminologie de référence, injectée via `{{glossary}}`.

## Intégration continue

`.github/workflows/docs-translate.yml` :

- **à chaque push** sur `main`/`develop` touchant `docs/fr/**` : traduction des
  pages manquantes/modifiées puis ouverture automatique d'une pull request
  (nécessite le secret `LLM_API_KEY` ; sans secret, le job est ignoré) ;
- **à chaque pull request** touchant `docs/` : vérification non bloquante qu'aucune
  page ne manque dans les langues secondaires.
