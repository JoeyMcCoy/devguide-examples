<?php

$cluster = new CouchbaseCluster('couchbase://localhost');
$bucket = $cluster->openBucket('default');

$RANDOM_NUMBER = rand(0, 10000000);
$bucket->upsert('user:'.$RANDOM_NUMBER, array(
    "name" => array("Brass", "Doorknob"),
    "email" => "brass.doorknob@juno.com",
    "random" => $RANDOM_NUMBER)
);

$query = CouchbaseN1qlQuery::fromString(
    'SELECT name, email, random, META(default).id FROM default WHERE $1 IN name'
);
$query->options['args'] = array('Brass');
// If this line is removed, the latest 'random' field might not be present
$query->consistency(CouchbaseN1qlQuery::REQUEST_PLUS);

printf("Expecting random: %d\n",  $RANDOM_NUMBER);
$result = $bucket->query($query);
foreach ($result->rows as $row) {
    printf("Name: %s, Email: %s, Random: %d\n", implode(" ", $row->name), $row->email, $row->random);
    if ($row->random == $RANDOM_NUMBER) {
        echo "!!! Found or newly inserted document !!!\n";
    }
    if (getenv("REMOVE_DOORKNOBS")) {
            echo "Removing " . $row->id . " (Requested via env)\n";
        $bucket->remove($row->id);
    }
}

// More light-weight way to do this is to use AT_PLUS consistency
// It will require mutation tokens enabled during connection.
$cluster = new CouchbaseCluster('couchbase://localhost?fetch_mutation_tokens=true');
$bucket = $cluster->openBucket('default');

$RANDOM_NUMBER = rand(0, 10000000);
$result = $bucket->upsert('user:'.$RANDOM_NUMBER, array(
    "name" => array("Brass", "Doorknob"),
    "email" => "brass.doorknob@juno.com",
    "random" => $RANDOM_NUMBER)
);
// construct mutation state from the list of mutation results
$mutationState = CouchbaseMutationState::from(array($result));

$query = CouchbaseN1qlQuery::fromString(
    'SELECT name, email, random, META(default).id FROM default WHERE $1 IN name'
);
$query->options['args'] = array('Brass');
// If this line is removed, the latest 'random' field might not be present
$query->consistentWith($mutationState);

printf("Expecting random: %d\n",  $RANDOM_NUMBER);
$result = $bucket->query($query);
foreach ($result->rows as $row) {
    printf("Name: %s, Email: %s, Random: %d\n", implode(" ", $row->name), $row->email, $row->random);
    if ($row->random == $RANDOM_NUMBER) {
        echo "!!! Found or newly inserted document !!!\n";
    }
    if (getenv("REMOVE_DOORKNOBS")) {
            echo "Removing " . $row->id . " (Requested via env)\n";
        $bucket->remove($row->id);
    }
}