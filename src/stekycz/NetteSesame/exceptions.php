<?php

namespace stekycz\NetteSesame;


class RuntimeException extends \RuntimeException
{

}



class LogicException extends \LogicException
{

}



class MissingDsnException extends RuntimeException
{

}



class BadStatusException extends RuntimeException
{

}



class FileNotSpecifiedException extends RuntimeException
{

}



class FileNotFoundException extends RuntimeException
{

}



class FileNotReadableException extends RuntimeException
{

}



class PrefixNotSpecifiedException extends RuntimeException
{

}



class NamespaceNotSpecifiedException extends RuntimeException
{

}



class NotSelectedRepositoryException extends LogicException
{

}



class UnsupportedQueryLanguageException extends LogicException
{

}



class UnsupportedInputFormatException extends LogicException
{

}



class UnsupportedResultFormatException extends LogicException
{

}
