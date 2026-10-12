alias e := enter
alias cr := cache-rebuild

stop:
  docker compose down

start:
  docker compose up -d

enter:
  docker exec -it redresshub-drupal bash

drush *arg:
  docker exec -it redresshub-drupal drush {{arg}}

cache-rebuild:
  @docker exec redresshub-drupal drush cr

build:
    docker compose up -d --build

rebuild:
    @echo -e 'Rebuilding from scratch...\n\n'
    docker compose down -v
    docker compose up -d --build
    docker logs redresshub-drupal -f
