<?php

class FaqPage extends Page
{

    public function getMenuOptions()
    {
        return array("FAQ" => "faq");
    }

    public function viewMidPane()
    {
        echo "<span class='faq largeCorner'>";
        echo "<span class='bigText'>Frequently Asked Questions</span>";

        echo "<span class='question'>What is KillWhore?</span>";
        echo "<span class='answer'>KillWhore is a side project that I am doing \"just for fun\".  Designing a";
        echo " killboard from scratch has many challenges and hurdles to overcome, and is a tough but entertaining project.</span>";

        echo "<span class='question'>Hey, how come this killboard doesn't have X?</span>";
        echo "<span class='answer'>This killboard is Beta and far from full release.  I have plenty of things to add yet and other ideas to fulfill as well.</span>";

        echo "<span class='question'>Why is this killboard API only?  Why can't I just copy and paste my kills like everywhere else?</span>";
        echo "<span class='answer'>I work with the folks at <a href='http://evsco.net'>EVSCO</a> quite a bit, and one of the biggest ";
        echo "problems with a killboard of their size is duplicate mails, faked mails, and Sisi postings.  I've decided I don't want to deal with that ";
        echo "headache.  Requiring all kills to be posted via API removes buggy feeds, scandalous players, Singularity kills, and general quirkiness ";
        echo "from the equation and helps to keep the killboard statistics honest.</span>";

        echo "<span class='question'>OK, but what do you plan on doing with my API?</span>";
        echo "<span class='answer'>I plan on pulling kills from it.  That's it.  I also run <a target='_blank' href='http://evechatter.com'>EveChatter</a> ";
        echo "where i have hundreds of APIs in use for the forum.  If I do anything else with the APIs I would be violating the trust I ";
        echo "have thus far earned from the community.  Anyhow, with the upcoming API changes you can create API keys that will provide ";
        echo "nothing but the KillLog.  I'm eagerly awaiting these API changes as they will allow the community of Eve players to be ";
        echo "only slightly less paranoid when giving out API keys.";
        echo "</span>";

        echo "<span class='question'>Is this killboard open source? Where can I get the source?</span>";
        echo "<span class='answer'>It is open source!  The source code resides <a target='_blank' href='https://github.com/cvweiss/zKillBoard'>here at GitHub</a>.  ";
        echo "Feel free to browse the code, make fun of it, and even contribute if you have some programming skills.</span>";

        echo "<span class='question'>What do these side bars mean on the search pages?</span>";
        echo "<span class='answer'>Each page will show you the the five pilots, corps, alliances, and ships that were involved with your current search terms.</span>";

        echo "<span class='question'>Blah blah blah... how do I use this killboard?</a></span>";
        echo "<span class='answer'>I've designed this killboard with the intention to make searching easy.  Very easy!  If you've already ";
        echo "tried checking out different pages you may have already noticed that the URL specificies exactly what you are seeing. ";
        echo "<fieldset class='smallCorner'><legend class='smallCorner'>Example</legend>";
        echo "<ul><li><a href='http://killwhore.com/with/corp/Woopatang'>http://killwhore.com/with/corp/Woopatang</a>";
        echo "<br/>Shows kills involving the corporation Woopatang.</li>";
        echo "<li><a href='http://killwhore.com/with/alli/ROMANIAN-LEGION/against/alli/White+Noise.'>http://killwhore.com/with/alli/ROMANIAN-LEGION/against/alli/White Noise.</a>";
        echo "<br/>Shows kill involving the alliance ROMANIAN-LEGION against the alliance White Noise.</li></ul>";
        echo "</fieldset>";
        echo "</span>";

        echo "<span class='question'>That's fine and dandy, but what if I don't want to type that all the time for my pilot/corp/alliance?</span>";
        echo "<span class='answer'>If the Killboard knows about you then you have an automatic subdomain.";
        echo "<fieldset class='smallCorner'><legend class='smallCorner'>Example</legend>";
        echo "<ul>";
        echo "<li><a href='http://squizz_caphinator.killwhore.com'>http://squizz_caphinator.killwhore.com</a> Kills for pilot Squizz Caphinator</li>";
        echo "<li><a href='http://woopatang.killwhore.com'>http://woopatang.killwhore.com</a> Kills for corporation Woopatang</li>";
        echo "<li><a href='http://primary.dot.killwhore.com'>'http://primary.dot.killwhore.com</a> Kill for alliance Primary. (yes, I'm entertained by the dot too)</li>";
        echo "</ul>";
        echo "</fieldset>";
        echo "<fieldset class='smallCorner'><legend class='smallCorner'>Basic rules for subdomains</legend>";
        echo "<ul>";
        echo "<li> Replace apostrophes with a - in the name.  So if your name is No'Way your domain would be http://no-way.killwhore.com</li>";
        echo "<li> Replace spaces with an underscore.  See the Squizz Caphinator example.</li>";
        echo "<li> If there is a . at the end of your name, replace it with .dot as I did with the Primary. example</li>";
        echo "<li> There may be some inconsistencies with these, but mostly they should Just Work (TM).</li>";
        echo "</ul>";
        echo "</fieldset></span>";

        echo "<span class='question'>What are some other search terms?</span>";
        echo "<span class='answer'>Here is a list of all search terms currently implemented.  Mixing amd matching should be pretty obvious I hope!<br/><br/>";
        echo "<fieldset class='smallCorner'><legend class='smallCorner'>Search Terms</legend>";
        echo "<ul>";
        echo "<li>kills - Show only the kills</li>";
        echo "<li>losses - Show only the losses</li>";
        echo "<li>pilot - Filter to a specific pilot.  <br/>Example <a href='http://killwhore.com/with/pilot/Squizz+Caphinator'>http://killwhore.com/with/pilot/Squizz+Caphinator</a></li>";
        echo "<li>corp - Filter to a specific corp.  <br/>Example <a href='http://killwhore.com/with/corp/Woopatang'>http://killwhore.com/with/corp/Woopatang</a></li>";
        echo "<li>alli - Filter to a specific alliance.  <br/>Example <a href='http://killwhore.com/with/alli/Primary.'>http://killwhore.com/with/alli/Primary.</a></li>";
        echo "<li>ship - Filter to a specific ship type.  <br/>Example <a href='http://killwhore.com/with/ship/Drake'>http://killwhore.com/with/ship/Drake</a></li>";
        echo "<li>finalBlow - Used with pilot/corp/alli/ship, will only show kills where the pilot/corp/alli/ship got the final blow on a victim.  <br/>Example <a href='http://killwhore.com/with/ship/Drake/finalBlow'>http://killwhore.com/with/ship/Drake/finalBlow</a></li>";
        echo "<li>with - This filter states that all pilots, corps, alliances, and ships following are considered \"friendly.\"</li>";
        echo "<li>against - This term states that all pilots, corps, alliances, and ships following are considered \"hostile.\"</li>";
        echo "<li>system - Show kills within a certain solar system.  <br/>Example: <a href='http://killwhore.com/with/system/Madirmilire'>http://killwhore.com/with/system/Madirmilire</a></li>";
        echo "<li>year, month, day - These terms allow you to filter to a certain year, month, or even day.  If any are ommitted the current year, month, or day is subsitited.</li>";
        echo "<li>date - Like above, allows you to filter to a certain YYYYMMDD is an easier to read format.  <br/>Example: <a href='http://killwhore.com/date/20110527'>http://killwhore.com/date/20110527</a></li>";
        echo "<li>related - Typically used with system, will show you related kills to the killmail you are currently viewing.  Kills are considered related if they happened during the hour before, hour during, ";
        echo "or hour after of the specified time.  <br/>Example: <a href='http://killwhore.com/system/5-CQDA/related/2011052502'>http://killwhore.com/system/5-CQDA/related/2011052502</a></li>";
        echo "</ul></fieldset></span>";

        echo "<span class='question'>Who the hell are you again?</span>";
        echo "<span class='answer'><img src='http://image.zzeve.com/Character/1633218082_64.jpg'/>&nbsp;&nbsp;&nbsp;I am <a href='http://squizz_caphinator.killwhore.com'>Squizz Caphinator</a>.  ";
        echo "I love to shoot stuff :)<br/><br/><img src='http://zzeve.com/sigs/SquizzSigEveO.png'/></span>";

        echo "</span>"; // faq
    }
}
