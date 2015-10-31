# AigisIRC
## IRC bot written in PHP.

### Dependencies

Packages (WAMP on Windows has all of this): PHP5 and MySQL (for the LinkInfo plugin, you also need curl)

PHP modules: MySQLnd, cURL (for link parsing), PCRE, PCNTL (for restart command)

If you don't want to/can't get these modules, just don't include the plugins that use them. However, AigisIRC's core requires PCRE, making it the only absolutely required PHP module.

### How to use

1. Edit the config.ini file to set up netwoks.
2. Make the "aigis" file executable.
3. Run the following command:
```
./aigis NetworkName
```

### Where in IRC to see Aigis in action

On Rizon (as Aigis or the alt nick, NaotoShirogane): #Aigis, #dprk, #plex and #rice (owner nick is lunarmage)

Send me a message on either Rizon or through GitHub if you want me to run Aigis in your IRC network. Aigis joins any channel instantly when invited if on Rizon.
