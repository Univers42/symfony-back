// Runs once on first container start (mongo official image entrypoint).
// Creates the application database and a non-root user with readWrite on it.
const appDb       = process.env.MONGO_APP_DB       || 'baas';
const appUser     = process.env.MONGO_APP_USER     || 'baas';
const appPassword = process.env.MONGO_APP_PASSWORD || 'baas_app_pwd';

db = db.getSiblingDB(appDb);

if (!db.getUser(appUser)) {
    db.createUser({
        user: appUser,
        pwd:  appPassword,
        roles: [{ role: 'readWrite', db: appDb }, { role: 'dbAdmin', db: appDb }],
    });
    print(`==> Created Mongo user '${appUser}' on db '${appDb}'`);
} else {
    print(`==> Mongo user '${appUser}' already exists on db '${appDb}'`);
}
